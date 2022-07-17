<?php

namespace Drupal\static_export\Controller;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Url;
use Drupal\static_export\Entity\ExportableEntityInterface;
use Drupal\static_export\Event\ExportableEntityListEvents;
use Drupal\static_export\Exporter\Data\Resolver\DataResolverPluginManagerInterface;
use Drupal\static_export\Exporter\Output\Config\ExporterOutputConfigFactoryInterface;
use Drupal\static_export\Exporter\Output\Formatter\OutputFormatterPluginManagerInterface;
use Drupal\static_suite\Event\DataEvent;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Provides a listing of Exportable Entities.
 */
class ExportableEntityListBuilder extends ConfigEntityListBuilder {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcher
   */
  protected $eventDispatcher;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The output formatter manager.
   *
   * @var \Drupal\static_export\Exporter\Output\Formatter\OutputFormatterPluginManagerInterface
   */
  protected $outputFormatterManager;

  /**
   * The resolver manager.
   *
   * @var \Drupal\static_export\Exporter\Data\Resolver\DataResolverPluginManagerInterface
   */
  protected $dataResolverManager;

  /**
   * The output configuration factory.
   *
   * @var \Drupal\static_export\Exporter\Output\Config\ExporterOutputConfigFactoryInterface
   */
  protected $exporterOutputConfigFactory;

  /**
   * Constructs a new EntityListBuilder object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The entity storage class.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher service.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager.
   * @param \Drupal\static_export\Exporter\Output\Formatter\OutputFormatterPluginManagerInterface $outputFormatterManager
   *   The output formatter manager.
   * @param \Drupal\static_export\Exporter\Data\Resolver\DataResolverPluginManagerInterface $dataResolverManager
   *   The data resolver manager.
   * @param \Drupal\static_export\Exporter\Output\Config\ExporterOutputConfigFactoryInterface $exporterOutputConfigFactory
   *   The output configuration factory.
   */
  public function __construct(EntityTypeInterface $entity_type, EntityStorageInterface $storage, ConfigFactoryInterface $config_factory, EventDispatcherInterface $event_dispatcher, LanguageManagerInterface $languageManager, OutputFormatterPluginManagerInterface $outputFormatterManager, DataResolverPluginManagerInterface $dataResolverManager, ExporterOutputConfigFactoryInterface $exporterOutputConfigFactory) {
    parent::__construct($entity_type, $storage);
    $this->configFactory = $config_factory;
    $this->eventDispatcher = $event_dispatcher;
    $this->languageManager = $languageManager;
    $this->outputFormatterManager = $outputFormatterManager;
    $this->dataResolverManager = $dataResolverManager;
    $this->exporterOutputConfigFactory = $exporterOutputConfigFactory;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity_type.manager')->getStorage($entity_type->id()),
      $container->get('config.factory'),
      $container->get('event_dispatcher'),
      $container->get('language_manager'),
      $container->get('plugin.manager.static_output_formatter'),
      $container->get('plugin.manager.static_data_resolver'),
      $container->get('static_export.entity_exporter_output_config_factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    if (!$this->configFactory->get('static_export.settings')
      ->get('exportable_entity.enabled')) {
      $configUrl = Url::fromRoute('static_export.exportable_entity.settings', [], ['absolute' => FALSE])
        ->toString();
      $this->messenger()
        ->addWarning($this->t('Export operations for entities are disabled. Please, enable them in the <a href="@url">settings</a> page.', ['@url' => $configUrl]));
    }

    $header['label'] = $this->t('Name');
    $header['resolver'] = $this->t('Resolver');
    $header['export_file'] = $this->t('Export file (inside data directory)');
    $header['export_referencing_entities'] = $this->t('Export referencing entities');
    $header['export_when_crud_happens_on_cli'] = $this->t('Export after a CRUD operation on CLI');
    $header['request_build_when_crud_exports_on_cli'] = $this->t('Request build after a CRUD operation on CLI');
    $header['is_statified_page'] = $this->t('It is a statified page');
    $finalHeader = $header + parent::buildHeader();

    // Dispatch an event so resolvers, output formatters, etc have a chance to
    // alter this table's output.
    $event = new DataEvent(['header' => $finalHeader]);
    $processedEvent = $this->eventDispatcher->dispatch($event, ExportableEntityListEvents::HEADER_BUILT);
    return $processedEvent->getData()['header'];
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function buildRow(EntityInterface $entity) {
    $row['label'] = $entity->label();
    if ($entity instanceof ExportableEntityInterface) {
      $dataResolverDefinition = $this->dataResolverManager->getDefinition($entity->getDataResolver(), FALSE);
      $row['resolver'] = $dataResolverDefinition ? $dataResolverDefinition['label'] : $this->t('N.A.');

      // Load the OutputFormatter plugin definition to get its extension.
      $definitions = $this->outputFormatterManager->getDefinitions();
      $format = $entity->getFormat();
      $extension = !empty($definitions[$format]) ? $definitions[$format]['extension'] : $format;

      // Use language "und" as a fallback and then replace it with a placeholder
      // to denote that language is a value that changes depending on the
      // exported entity.
      $outputConfig = $this->exporterOutputConfigFactory->create($entity->getDirectory(), $entity->getFilename(), $extension, $this->languageManager->getLanguage(LanguageInterface::LANGCODE_NOT_SPECIFIED));
      $row['export_file'] = str_replace(LanguageInterface::LANGCODE_NOT_SPECIFIED . "/", "__LANGCODE__/", $outputConfig->uri()
        ->getTarget());
      $row['export_referencing_entities'] = $entity->getExportReferencingEntities() ? $this->t('yes (recursion level: @recursion_level)', ['@recursion_level' => $entity->getRecursionLevel()]) : $this->t('no');
      $row['export_when_crud_happens_on_cli'] = $entity->getExportWhenCrudHappensOnCli() ? $this->t('yes') : $this->t('no');
      $row['request_build_when_crud_exports_on_cli'] = $entity->getRequestBuildWhenCrudExportsOnCli() ? $this->t('yes') : $this->t('no');
      $row['is_statified_page'] = $entity->getIsStatifiedPage() ? $this->t('yes') : $this->t('no');
      $finalRow = $row + parent::buildRow($entity);

      // Dispatch an event so resolvers, output formatters, etc have a chance to
      // alter this table's output.
      $event = new DataEvent(['row' => $finalRow, 'entity' => $entity]);
      $processedEvent = $this->eventDispatcher->dispatch($event, ExportableEntityListEvents::ROW_BUILT);
      return $processedEvent->getData()['row'];
    }
    return $row;
  }

}
