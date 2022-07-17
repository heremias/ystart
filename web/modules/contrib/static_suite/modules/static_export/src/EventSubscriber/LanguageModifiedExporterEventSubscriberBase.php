<?php

namespace Drupal\static_export\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\static_export\Event\LanguageModifiedEvents;
use Drupal\static_export\Exporter\ExporterPluginInterface;
use Drupal\static_export\Exporter\Stack\ExporterStackExecutorInterface;
use Drupal\static_export\Exporter\Type\Custom\CustomExporterPluginManagerInterface;
use Drupal\static_suite\Utility\StaticSuiteUtilsInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event subscriber for language metadata exporter.
 *
 * Exports data when system site, a language entity, language negotiation or
 * language type is updated.
 */
abstract class LanguageModifiedExporterEventSubscriberBase implements EventSubscriberInterface {

  /**
   * Drupal config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * Custom exporter manager.
   *
   * @var \Drupal\static_export\Exporter\Type\Custom\CustomExporterPluginManagerInterface
   */
  protected CustomExporterPluginManagerInterface $customExporterPluginManager;

  /**
   * Static Suite Utils.
   *
   * @var \Drupal\static_suite\Utility\StaticSuiteUtilsInterface
   */
  protected StaticSuiteUtilsInterface $staticSuiteUtils;

  /**
   * The exporter that will handle the event.
   *
   * @var \Drupal\static_export\Exporter\ExporterPluginInterface
   */
  protected ExporterPluginInterface $exporter;

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
   * @param \Drupal\static_export\Exporter\Type\Custom\CustomExporterPluginManagerInterface $customExporterPluginManager
   *   Custom exporter manager.
   * @param \Drupal\static_suite\Utility\StaticSuiteUtilsInterface $staticSuiteUtils
   *   Static Suite Utils.
   * @param \Drupal\static_export\Exporter\Stack\ExporterStackExecutorInterface $exporterStackExecutor
   *   The exporter stack executor.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function __construct(ConfigFactoryInterface $configFactory, CustomExporterPluginManagerInterface $customExporterPluginManager, StaticSuiteUtilsInterface $staticSuiteUtils, ExporterStackExecutorInterface $exporterStackExecutor) {
    $this->configFactory = $configFactory;
    $this->customExporterPluginManager = $customExporterPluginManager;
    $this->staticSuiteUtils = $staticSuiteUtils;
    $this->exporterStackExecutor = $exporterStackExecutor;
    $this->exporter = $this->customExporterPluginManager->createInstance($this->getExporterId());
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[LanguageModifiedEvents::LANGUAGE_MODIFIED][] = ['export'];
    return $events;
  }

  /**
   * Exports language configuration data.
   */
  public function export(): void {
    $config = $this->configFactory->get('static_export.settings');

    // Early opt-out if STATIC_EXPORT_STOP_LISTENING_TO_CRUD_EVENTS environment
    // variable is present.
    if (getenv('STATIC_EXPORT_STOP_LISTENING_TO_CRUD_EVENTS', TRUE)) {
      return;
    }

    // If running on CLI, check if an export operation must be executed.
    if ($this->staticSuiteUtils->isRunningOnCli() && !$config->get('exportable_config.export_when_crud_happens_on_cli')) {
      return;
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
        $requestBuild = !$config->get('exportable_config.request_build_when_crud_exports_on_cli');
      }
    }

    $options = [
      'operation' => [
        'id' => ExporterPluginInterface::OPERATION_WRITE,
        'args' => $this->getExporterArgs(),
      ],
      'request-build' => $requestBuild,
    ];
    $this->exporterStackExecutor->add($this->getExporterId(), $this->exporter, $options);
  }

  /**
   * Get the id of the exporter being executed.
   *
   * @return string
   *   The id of the exporter being executed.
   */
  abstract protected function getExporterId(): string;

  /**
   * Get an array of arguments to be passed to the exporter.
   *
   * Classes that extend this abstract class should override this method to
   * provide their own arguments if required.
   *
   * @return array
   *   Array of arguments to be passed to the exporter.
   */
  protected function getExporterArgs(): array {
    return [];
  }

}
