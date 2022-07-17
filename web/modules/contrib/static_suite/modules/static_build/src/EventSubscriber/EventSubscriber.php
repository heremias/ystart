<?php

namespace Drupal\static_build\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EditorialContentEntityBase;
use Drupal\Core\StreamWrapper\StreamWrapperInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Drupal\Core\TempStore\SharedTempStoreFactory;
use Drupal\node\Entity\Node;
use Drupal\static_build\Plugin\StaticBuilderPluginInterface;
use Drupal\static_build\Plugin\StaticBuilderPluginManagerInterface;
use Drupal\static_export\Event\StaticExportEvent;
use Drupal\static_export\Event\StaticExportEvents;
use Drupal\static_export\Exporter\Type\Entity\EntityExporterPluginInterface;
use Drupal\static_suite\Entity\EntityUtils;
use Drupal\static_suite\Utility\StaticSuiteUtilsInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event subscriber for Static Build.
 */
class EventSubscriber implements EventSubscriberInterface {

  /**
   * Config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The stream wrapper manager.
   *
   * @var \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface
   */
  protected $streamWrapperManager;

  /**
   * The shared tempstore factory.
   *
   * @var \Drupal\Core\TempStore\SharedTempStoreFactory
   */
  protected $tempStoreFactory;

  /**
   * Static Builder Manager.
   *
   * @var \Drupal\static_build\Plugin\StaticBuilderPluginManagerInterface
   */
  protected $staticBuilderPluginManager;

  /**
   * Entity Utils.
   *
   * @var \Drupal\static_suite\Entity\EntityUtils
   */
  protected $entityUtils;

  /**
   * Static Suite utils.
   *
   * @var \Drupal\static_suite\Utility\StaticSuiteUtilsInterface
   */
  protected $staticSuiteUtils;

  /**
   * Constructs the subscriber.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Config factory.
   * @param \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface $streamWrapperManager
   *   The stream wrapper manager.
   * @param \Drupal\Core\TempStore\SharedTempStoreFactory $tempStoreFactory
   *   The shared tempstore factory.
   * @param \Drupal\static_build\Plugin\StaticBuilderPluginManagerInterface $staticBuilderPluginManager
   *   Static Builder Manager.
   * @param \Drupal\static_suite\Entity\EntityUtils $entityUtils
   *   Utils for working with entities.
   * @param \Drupal\static_suite\Utility\StaticSuiteUtilsInterface $static_suite_utils
   *   Static Suite utils.
   */
  public function __construct(ConfigFactoryInterface $config_factory, StreamWrapperManagerInterface $streamWrapperManager, SharedTempStoreFactory $tempStoreFactory, StaticBuilderPluginManagerInterface $staticBuilderPluginManager, EntityUtils $entityUtils, StaticSuiteUtilsInterface $static_suite_utils) {
    $this->configFactory = $config_factory;
    $this->streamWrapperManager = $streamWrapperManager;
    $this->tempStoreFactory = $tempStoreFactory;
    $this->staticBuilderPluginManager = $staticBuilderPluginManager;
    $this->entityUtils = $entityUtils;
    $this->staticSuiteUtils = $static_suite_utils;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[StaticExportEvents::CHAINED_STEP_END][] = ['requestLiveBuild'];
    $events[StaticExportEvents::CHAINED_STEP_END][] = ['requestPreviewBuild'];
    return $events;
  }

  /**
   * Request a live build.
   *
   * @param \Drupal\static_export\Event\StaticExportEvent $event
   *   The Static Export event.
   *
   * @return \Drupal\static_export\Event\StaticExportEvent
   *   The processed event.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\static_suite\StaticSuiteException
   */
  public function requestLiveBuild(StaticExportEvent $event): StaticExportEvent {
    return $this->requestBuild($event, StaticBuilderPluginInterface::RUN_MODE_LIVE);
  }

  /**
   * Request a preview build.
   *
   * @param \Drupal\static_export\Event\StaticExportEvent $event
   *   The Static Export event.
   *
   * @return \Drupal\static_export\Event\StaticExportEvent
   *   The processed event.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\static_suite\StaticSuiteException
   */
  public function requestPreviewBuild(StaticExportEvent $event): StaticExportEvent {
    return $this->requestBuild($event, StaticBuilderPluginInterface::RUN_MODE_PREVIEW);
  }

  /**
   * Request a live build.
   *
   * @param \Drupal\static_export\Event\StaticExportEvent $event
   *   The Static Export event.
   * @param string $runMode
   *   Build run mode, live or preview.
   *
   * @return \Drupal\static_export\Event\StaticExportEvent
   *   The processed event.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\static_suite\StaticSuiteException
   * @throws \Drupal\static_suite\StaticSuiteUserException
   */
  protected function requestBuild(StaticExportEvent $event, string $runMode): StaticExportEvent {
    $exporter = $event->getExporter();

    // Check if received event should request a build.
    if (!$exporter->mustRequestBuild()) {
      $exporter->logMessage('[build] ' . $runMode . ' | Exporter is not allowed to request a build.');
      return $event;
    }

    // Check if received FileCollections contains changes.
    $fileCollectionGroup = $exporter->getResultingFileCollectionGroup();
    if (!$fileCollectionGroup || !$fileCollectionGroup->isAnyFileCollectionExecuted()) {
      $exporter->logMessage('[build] ' . $runMode . ' | No changes detected in received FileCollectionGroup. No build will we triggered.');
      return $event;
    }

    // Check if the selected stream wrapper is supported.
    $scheme = $this->configFactory->get('static_export.settings')
      ->get('uri.scheme');
    $streamWrapper = $this->streamWrapperManager->getViaScheme($scheme);
    if (!$streamWrapper) {
      $exporter->logMessage('[build] ' . $runMode . ' | No stream wrapper available for scheme "' . $scheme . '://"');
      return $event;
    }
    if ($streamWrapper::getType() !== StreamWrapperInterface::LOCAL_NORMAL) {
      $exporter
        ->logMessage('[build] ' . $runMode . ' | The stream wrapper "' . $streamWrapper->getUri() . '" is remote and can not be used to build a site.');
      return $event;
    }

    // If run mode is live, check if entity is published or status has changed.
    if ($runMode === StaticBuilderPluginInterface::RUN_MODE_LIVE && $exporter instanceof EntityExporterPluginInterface) {
      $entity = $exporter->getExporterItem();

      // Check if it's published.
      $isPublished = TRUE;
      if ($entity instanceof Node) {
        $isPublished = $entity->isPublished();
      }

      // Check if status has changed: if status is unpublished, but status
      // has changed (from published to unpublished, or vice versa) we
      // must build it.
      $statusHasChanged = FALSE;
      if ($entity instanceof EditorialContentEntityBase) {
        $statusHasChanged = $this->entityUtils->hasStatusChanged($entity);
      }

      if (!$isPublished && !$statusHasChanged) {
        $exporter->logMessage('[build] ' . $runMode . " | Exporter entity is not published nor status has changed. No build will we triggered.");
        return $event;
      }
    }

    // Check if received FileCollections matches paths that request a build.
    $executedFilePaths = $fileCollectionGroup->getExecutedFilePathsFromAllFileCollections();
    $exporter->logMessage('[build] ' . $runMode . " | Changed files in received FileCollectionGroup:\n" . implode("\n", $executedFilePaths));
    $changedFilesRegExpList = $this->getBuildTriggerRegExpList($runMode);
    $exporter
      ->logMessage('[build] ' . $runMode . " | RegExp List to match changed files:\n" . implode("\n", $changedFilesRegExpList));
    if (is_array($changedFilesRegExpList) && count($changedFilesRegExpList) && !$this->staticSuiteUtils->isAnyItemMatchingRegexpList($executedFilePaths, $changedFilesRegExpList)) {
      $exporter
        ->logMessage('[build] ' . $runMode . ' | No changed files match RegExp List. No build triggered.');
      return $event;
    }

    $exporter
      ->logMessage('[build] ' . $runMode . ' | Changes detected in received FileCollectionGroup. Build triggered.');

    $builders = $this->configFactory->get('static_build.settings')
      ->get($runMode . '.builders');
    if ($builders && is_array($builders)) {
      $localBuilders = $this->staticBuilderPluginManager->getLocalDefinitions();
      foreach ($builders as $builderId) {
        // A deployment must be requested:
        // 1) When running on CLI, don't request a deployment unless manually
        // requested.
        // 2) When running on a web server, request a deployment only for live
        // local builders.
        $mustRequestDeploy = FALSE;
        if (PHP_SAPI === 'cli') {
          $mustRequestDeploy = $exporter->mustRequestDeploy();
        }
        elseif ($runMode === StaticBuilderPluginInterface::RUN_MODE_LIVE && array_key_exists($builderId, $localBuilders)) {
          $mustRequestDeploy = TRUE;
        }
        $staticBuilder = $this->staticBuilderPluginManager->getInstance([
          'plugin_id' => $builderId,
          'configuration' => [
            'run-mode' => $runMode,
            'console-output' => $exporter->getConsoleOutput(),
            'request-deploy' => $mustRequestDeploy,
          ],
        ]);

        // Finally, trigger a build.
        $staticBuilder->init();
      }
    }

    return $event;
  }

  /**
   * Get build_trigger_regexp_list config value.
   *
   * @param string $runMode
   *   Build run mode, live or preview.
   *
   * @return array
   *   Array with Regular Expressions or null otherwise.
   */
  public function getBuildTriggerRegExpList(string $runMode): array {
    $config = $this->configFactory->get('static_build.settings');
    $regExpList = $config->get($runMode . '.build_trigger_regexp_list');
    if (is_array($regExpList)) {
      return $regExpList;
    }
    return [];
  }

}
