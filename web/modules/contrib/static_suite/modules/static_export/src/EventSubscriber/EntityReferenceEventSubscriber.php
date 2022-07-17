<?php

namespace Drupal\static_export\EventSubscriber;

use Drupal\node\Entity\Node;
use Drupal\static_export\Entity\ExportableEntityManagerInterface;
use Drupal\static_export\Event\StaticExportEvent;
use Drupal\static_export\Event\StaticExportEvents;
use Drupal\static_export\Exporter\ExporterPluginInterface;
use Drupal\static_export\Exporter\Type\Entity\EntityExporterPluginManagerInterface;
use Drupal\static_export\File\FileCollection;
use Drupal\static_suite\Entity\EntityReferenceFinderInterface;
use Drupal\static_suite\Entity\EntityUtils;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event subscriber for exporting entity references.
 */
class EntityReferenceEventSubscriber implements EventSubscriberInterface {

  /**
   * Entity exporter Manager.
   *
   * @var \Drupal\static_export\Exporter\Type\Entity\EntityExporterPluginManagerInterface
   */
  protected $entityExporterPluginManager;

  /**
   * Entity reference finder.
   *
   * @var \Drupal\static_suite\Entity\EntityReferenceFinderInterface
   */
  protected $entityReferenceFinder;

  /**
   * Exportable entity manager.
   *
   * @var \Drupal\static_export\Entity\ExportableEntityManagerInterface
   */
  protected $exportableEntityManager;

  /**
   * Entity Utils.
   *
   * @var \Drupal\static_suite\Entity\EntityUtils
   */
  protected $entityUtils;

  /**
   * Constructs the EntityEventSubscriber object.
   *
   * @param \Drupal\static_export\Exporter\Type\Entity\EntityExporterPluginManagerInterface $entityExporterPluginManager
   *   Exporter Manager.
   * @param \Drupal\static_suite\Entity\EntityReferenceFinderInterface $entityReferenceFinder
   *   Entity reference finder.
   * @param \Drupal\static_export\Entity\ExportableEntityManagerInterface $exportableEntityManager
   *   Exportable entity manager.
   * @param \Drupal\static_suite\Entity\EntityUtils $entity_utils
   *   Utils for working with entities.
   */
  public function __construct(EntityExporterPluginManagerInterface $entityExporterPluginManager, EntityReferenceFinderInterface $entityReferenceFinder, ExportableEntityManagerInterface $exportableEntityManager, EntityUtils $entity_utils) {
    $this->entityExporterPluginManager = $entityExporterPluginManager;
    $this->entityReferenceFinder = $entityReferenceFinder;
    $this->exportableEntityManager = $exportableEntityManager;
    $this->entityUtils = $entity_utils;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[StaticExportEvents::WRITE_START][] = ['onWriteStarts'];
    return $events;
  }

  /**
   * Reacts to a StaticExportEvents::WRITE_START event.
   *
   * @param \Drupal\static_export\Event\StaticExportEvent $event
   *   The Static Export event.
   *
   * @return \Drupal\static_export\Event\StaticExportEvent
   *   The processed event.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\static_suite\StaticSuiteUserException
   * @throws \Drupal\static_suite\StaticSuiteException
   */
  public function onWriteStarts(StaticExportEvent $event): StaticExportEvent {
    $eventExporter = $event->getExporter();
    if ($eventExporter->getPluginId() === 'entity') {
      $entity = $eventExporter->getExporterItem();

      // Check if it's published.
      $isPublished = TRUE;
      if ($entity instanceof Node) {
        $isPublished = $entity->isPublished();
      }

      // Check if status has changed: if status is unpublished, but status has
      // changed (from published to unpublished, or vice versa) we must find
      // all referenced entities to update them.
      $statusHasChanged = FALSE;
      if ($entity instanceof Node) {
        $statusHasChanged = $this->entityUtils->hasStatusChanged($entity);
      }

      // Check if this Exportable Entity must export its referencing entities.
      /** @var \Drupal\static_export\Entity\ExportableEntity $exportableEntity */
      $exportableEntity = $this->entityUtils->loadEntity(
        'exportable_entity',
        $entity->getEntityTypeId() . '.' . $entity->bundle()
      );
      $exportReferencingEntities = $exportableEntity->getExportReferencingEntities();

      // Search referenced entities only for the master export (the first one)
      // and if it's configured to do that
      // and is not in standalone mode
      // and it's not an unpublished entity.
      if (
        $eventExporter->isMasterExport() &&
        $exportReferencingEntities && ($isPublished || $statusHasChanged) &&
        !$eventExporter->isStandalone()
      ) {
        $fileCollection = $this->exportReferencedEntities($eventExporter);
        $event->getFileCollection()->merge($fileCollection);
      }
    }
    return $event;
  }

  /**
   * Exports entities referenced by the original one.
   *
   * @param \Drupal\static_export\Exporter\ExporterPluginInterface $entityExporter
   *   The event entity exporter.
   *
   * @return \Drupal\static_export\File\FileCollection
   *   A file collection.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\static_suite\StaticSuiteUserException
   * @throws \Drupal\static_suite\StaticSuiteException
   */
  protected function exportReferencedEntities(ExporterPluginInterface $entityExporter): FileCollection {
    $fileCollection = new FileCollection($entityExporter->uniqueId());
    $entityBeingExported = $entityExporter->getExporterItem();
    $exportableEntity = $this->exportableEntityManager->getExportableEntity($entityBeingExported);
    if ($exportableEntity) {
      $relatedEntities = $this->entityReferenceFinder->findReferences(
        $entityBeingExported,
        $exportableEntity->getRecursionLevel()
      );
      foreach ($relatedEntities as $relatedEntity) {
        // Avoid exporting a related entity that is not exportable.
        if (!$this->exportableEntityManager->isExportable($relatedEntity)) {
          continue;
        }

        // We need a new instance, so create a new one instead of getting it.
        $exporter = $this->entityExporterPluginManager->createDefaultInstance();
        $relatedEntitiesFileCollectionGroup = $exporter->makeSlaveOf($entityExporter)
          ->export(
            [
              'entity' => $relatedEntity,
            ],
            TRUE,
            $entityExporter->mustLogToFile(),
            $entityExporter->isLock()
          );

        $fileCollection->mergeMultiple($relatedEntitiesFileCollectionGroup->getFileCollections());
      }
    }
    return $fileCollection;
  }

}
