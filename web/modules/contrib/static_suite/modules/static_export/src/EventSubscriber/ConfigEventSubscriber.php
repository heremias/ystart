<?php

namespace Drupal\static_export\EventSubscriber;

use Drupal\Core\Config\ConfigCrudEvent;
use Drupal\Core\Config\ConfigEvents;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\language\Config\LanguageConfigOverrideCrudEvent;
use Drupal\language\Config\LanguageConfigOverrideEvents;
use Drupal\static_export\Exporter\ExporterPluginInterface;
use Drupal\static_export\Exporter\Stack\ExporterStackExecutorInterface;
use Drupal\static_export\Exporter\Type\Config\ConfigExporterPluginManagerInterface;
use Drupal\static_export\Messenger\MessengerInterface;
use Drupal\static_suite\Utility\StaticSuiteUtilsInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event subscriber for config entities.
 */
class ConfigEventSubscriber implements EventSubscriberInterface {

  /**
   * Drupal config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Config exporter manager.
   *
   * @var \Drupal\static_export\Exporter\Type\Config\ConfigExporterPluginManagerInterface
   */
  protected $configExporterManager;

  /**
   * Messenger service.
   *
   * @var \Drupal\static_export\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Static Suite Utils.
   *
   * @var \Drupal\static_suite\Utility\StaticSuiteUtilsInterface
   */
  protected $staticSuiteUtils;

  /**
   * The exporter that will handle the event.
   *
   * @var \Drupal\static_export\Exporter\ExporterPluginInterface
   */
  protected $exporter;

  /**
   * The exporter stack executor.
   *
   * @var \Drupal\static_export\Exporter\Stack\ExporterStackExecutorInterface
   */
  protected ExporterStackExecutorInterface $exporterStackExecutor;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   Drupal config factory.
   * @param \Drupal\static_export\Exporter\Type\Config\ConfigExporterPluginManagerInterface $configExporterManager
   *   Config exporter manager.
   * @param \Drupal\static_export\Messenger\MessengerInterface $messenger
   *   Messenger service.
   * @param \Drupal\static_suite\Utility\StaticSuiteUtilsInterface $staticSuiteUtils
   *   Static Suite Utils.
   * @param \Drupal\static_export\Exporter\Stack\ExporterStackExecutorInterface $exporterStackExecutor
   *   The exporter stack executor.
   */
  public function __construct(ConfigFactoryInterface $configFactory, ConfigExporterPluginManagerInterface $configExporterManager, MessengerInterface $messenger, StaticSuiteUtilsInterface $staticSuiteUtils, ExporterStackExecutorInterface $exporterStackExecutor) {
    $this->configFactory = $configFactory;
    $this->configExporterManager = $configExporterManager;
    $this->messenger = $messenger;
    $this->staticSuiteUtils = $staticSuiteUtils;
    $this->exporterStackExecutor = $exporterStackExecutor;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[ConfigEvents::SAVE][] = ['configUpdate'];
    $events[ConfigEvents::RENAME][] = ['configUpdate'];
    $events[ConfigEvents::DELETE][] = ['configDelete'];
    $events[LanguageConfigOverrideEvents::SAVE_OVERRIDE][] = ['configSaveOverride'];
    $events[LanguageConfigOverrideEvents::DELETE_OVERRIDE][] = ['configDeleteOverride'];
    return $events;
  }

  /**
   * Reacts to a save event.
   *
   * @param \Drupal\Core\Config\ConfigCrudEvent $event
   *   The configuration event.
   *
   * @return bool
   *   TRUE if everything is OK.
   */
  public function configUpdate(ConfigCrudEvent $event): bool {
    return $this->configCrudEventExport($event->getConfig()
      ->getName(), ExporterPluginInterface::OPERATION_WRITE);
  }

  /**
   * Reacts to a save override event.
   *
   * @param \Drupal\language\Config\LanguageConfigOverrideCrudEvent $event
   *   The configuration event.
   *
   * @return bool
   *   TRUE if everything is OK.
   */
  public function configSaveOverride(LanguageConfigOverrideCrudEvent $event): bool {
    return $this->configCrudEventExport($event->getLanguageConfigOverride()
      ->getName(), ExporterPluginInterface::OPERATION_WRITE);
  }

  /**
   * Reacts to a delete event.
   *
   * Unlike ConfigEventSubscriber::configDeleteOverride, this method executes a
   * ExporterPluginInterface::OPERATION_DELETE operation on the exporter, since
   * the whole configuration object is being deleted, not only a translation.
   *
   * @param \Drupal\Core\Config\ConfigCrudEvent $event
   *   The configuration event.
   *
   * @return bool
   *   TRUE if everything is OK.
   */
  public function configDelete(ConfigCrudEvent $event): bool {
    return $this->configCrudEventExport($event->getConfig()
      ->getName(), ExporterPluginInterface::OPERATION_DELETE);
  }

  /**
   * Reacts to a delete override event.
   *
   * Even though this is a delete event, the operation executed by the exporter
   * must be ExporterPluginInterface::OPERATION_WRITE because when a
   * configuration translation is deleted, the configuration object still exists
   * and needs to be re-exported.
   *
   * @param \Drupal\language\Config\LanguageConfigOverrideCrudEvent $event
   *   The configuration event.
   *
   * @return bool
   *   TRUE if everything is OK.
   */
  public function configDeleteOverride(LanguageConfigOverrideCrudEvent $event): bool {
    return $this->configCrudEventExport(
      $event->getLanguageConfigOverride()->getName(),
      ExporterPluginInterface::OPERATION_WRITE
    );
  }

  /**
   * Exports or deletes a config.
   *
   * @param string $configObjectName
   *   The configuration object name that caused the event to fire.
   * @param string $operation
   *   "export" or "delete".
   *
   * @return bool
   *   TRUE on success
   */
  protected function configCrudEventExport(string $configObjectName, string $operation): bool {
    // Stop execution if export operations are disabled.
    if (!$this->configFactory->get('static_export.settings')
      ->get('exportable_config.enabled')) {
      return FALSE;
    }

    // This event is triggered by lots of CRUD operations. Reuse the same
    // exporter if this event is called more than once in the same thread.
    if (!$this->exporter) {
      $this->exporter = $this->configExporterManager->getDefaultInstance();
    }

    // This event is triggered by lots of CRUD operations, so we need to
    // filter it after calling the exporter. The exporter does the same,
    // checking its params, but it would throw an exception.
    if (!$this->exporter->isExportable(['name' => $configObjectName])) {
      return FALSE;
    }

    // If running on CLI, check if a export operation must be executed.
    if ($this->staticSuiteUtils->isRunningOnCli() && !$this->configFactory->get('static_export.settings')
      ->get('exportable_config.export_when_crud_happens_on_cli')) {
      return FALSE;
    }

    // Honor the value of STATIC_EXPORT_REQUEST_BUILD environmental variable.
    $requestBuildOverride = getenv('STATIC_EXPORT_REQUEST_BUILD', TRUE);
    if ($requestBuildOverride) {
      $requestBuild = filter_var($requestBuildOverride, FILTER_VALIDATE_BOOLEAN);
    }
    else {
      $requestBuild = TRUE;
      // If running on CLI, check if a build must be requested.
      if ($this->staticSuiteUtils->isRunningOnCli()) {
        $requestBuild = !$this->configFactory->get('static_export.settings')
          ->get('exportable_config.request_build_when_crud_exports_on_cli');
      }
    }

    $stackId = $this->exporter::class . '--write--' . $configObjectName;
    $options = [
      'operation' => [
        'id' => $operation,
        'args' => [
          'name' => $configObjectName,
        ],
      ],
      'request-build' => $requestBuild,
    ];
    $this->exporterStackExecutor->add($stackId, $this->exporter, $options);

    return TRUE;
  }

}
