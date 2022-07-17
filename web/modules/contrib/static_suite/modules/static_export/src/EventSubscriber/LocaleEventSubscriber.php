<?php

namespace Drupal\static_export\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\locale\LocaleEvent;
use Drupal\locale\LocaleEvents;
use Drupal\static_export\Exporter\ExporterPluginInterface;
use Drupal\static_export\Exporter\Stack\ExporterStackExecutorInterface;
use Drupal\static_export\Exporter\Type\Locale\LocaleExporterPluginManagerInterface;
use Drupal\static_export\Messenger\MessengerInterface;
use Drupal\static_suite\Utility\StaticSuiteUtilsInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event subscriber for locales.
 */
class LocaleEventSubscriber implements EventSubscriberInterface {

  /**
   * Drupal config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Locale exporter manager.
   *
   * @var \Drupal\static_export\Exporter\Type\Locale\LocaleExporterPluginManagerInterface
   */
  protected $localeExporterManager;

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
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Drupal config factory.
   * @param \Drupal\static_export\Exporter\Type\Locale\LocaleExporterPluginManagerInterface $localeExporterManager
   *   Exporter Manager.
   * @param \Drupal\static_export\Messenger\MessengerInterface $messenger
   *   Messenger service.
   * @param \Drupal\static_suite\Utility\StaticSuiteUtilsInterface $staticSuiteUtils
   *   Static Suite Utils.
   * @param \Drupal\static_export\Exporter\Stack\ExporterStackExecutorInterface $exporterStackExecutor
   *   The exporter stack executor.
   */
  public function __construct(ConfigFactoryInterface $config_factory, LocaleExporterPluginManagerInterface $localeExporterManager, MessengerInterface $messenger, StaticSuiteUtilsInterface $staticSuiteUtils, ExporterStackExecutorInterface $exporterStackExecutor) {
    $this->configFactory = $config_factory;
    $this->localeExporterManager = $localeExporterManager;
    $this->messenger = $messenger;
    $this->staticSuiteUtils = $staticSuiteUtils;
    $this->exporterStackExecutor = $exporterStackExecutor;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[LocaleEvents::SAVE_TRANSLATION][] = ['onSaveLocale'];
    return $events;
  }

  /**
   * Reacts to a save event.
   *
   * @param \Drupal\locale\LocaleEvent $event
   *   A locale save event.
   *
   * @return bool
   *   TRUE on success
   */
  public function onSaveLocale(LocaleEvent $event): bool {
    $config = $this->configFactory->get('static_export.settings');

    // Stop execution if export operations are disabled.
    if (!$config->get('exportable_locale.enabled')) {
      return FALSE;
    }

    // @todo - add support for exporting multiple files at once.
    $langCodes = $event->getLangCodes();

    // Multiple langcodes are possible, but, at this moment, only the first one
    // can be exported.
    $masterLangCode = array_shift($langCodes);
    // If running on CLI, check if a export operation must be executed.
    if ($this->staticSuiteUtils->isRunningOnCli() && !$config->get('exportable_locale.export_when_crud_happens_on_cli')) {
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
        $requestBuild = !$config->get('exportable_locale.request_build_when_crud_exports_on_cli');
      }
    }

    $exporter = $this->localeExporterManager->getDefaultInstance();
    $stackId = $exporter::class . '--write--' . $masterLangCode;
    $options = [
      'operation' => [
        'id' => ExporterPluginInterface::OPERATION_WRITE,
        'args' => [
          'langcode' => $masterLangCode,
        ],
      ],
      'request-build' => $requestBuild,
    ];
    $this->exporterStackExecutor->add($stackId, $exporter, $options);

    return TRUE;
  }

}
