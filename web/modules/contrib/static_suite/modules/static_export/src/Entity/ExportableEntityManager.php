<?php

namespace Drupal\static_export\Entity;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Entity\EntityInterface;
use Drupal\static_suite\Entity\EntityUtilsInterface;

/**
 *
 */
class ExportableEntityManager implements ExportableEntityManagerInterface {

  /**
   * Entity utils.
   *
   * @var \Drupal\static_suite\Entity\EntityUtilsInterface
   */
  protected $entityUtils;

  /**
   * ExportableEntityManager constructor.
   *
   * @param \Drupal\static_suite\Entity\EntityUtilsInterface $entityUtils
   *   Entity utils.
   */
  public function __construct(EntityUtilsInterface $entityUtils) {
    $this->entityUtils = $entityUtils;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getAllExportableEntities(): array {
    $allExportableEntities = [];
    /** @var \Drupal\static_export\Entity\ExportableEntityInterface[] $exportableEntityIds */
    $exportableEntityIds = $this->entityUtils->getEntityIds('exportable_entity');
    foreach ($exportableEntityIds as $exportableEntityId) {
      $allExportableEntities[] = $this->entityUtils->loadEntity('exportable_entity', $exportableEntityId);
    }
    return $allExportableEntities;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getExportableEntitiesThatAreStatifiedPages(): array {
    $exportableEntitiesThatAreStatifiedPages = [];
    $allExportableEntities = $this->getAllExportableEntities();
    foreach ($allExportableEntities as $exportableEntity) {
      if ($exportableEntity->getIsStatifiedPage()) {
        $exportableEntitiesThatAreStatifiedPages[] = $exportableEntity;
      }
    }
    return $exportableEntitiesThatAreStatifiedPages;
  }

  /**
   * {@inheritdoc}
   */
  public function getExportableEntity(EntityInterface $entity): ?ExportableEntityInterface {
    try {
      $exportableEntity = $this->entityUtils->loadEntity(
        'exportable_entity',
        $entity->getEntityTypeId() . '.' . $entity->bundle()
      );
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
      return NULL;
    }

    if ($exportableEntity instanceof ExportableEntityInterface) {
      return $exportableEntity;
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function isExportable(EntityInterface $entity): bool {
    $exportableEntity = $this->getExportableEntity($entity);
    return $exportableEntity && $exportableEntity->status();
  }

}
