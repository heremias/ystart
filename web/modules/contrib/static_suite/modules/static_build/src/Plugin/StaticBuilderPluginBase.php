<?php

namespace Drupal\static_build\Plugin;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\StreamWrapper\StreamWrapperInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\static_build\Event\StaticBuildEvent;
use Drupal\static_build\Event\StaticBuildEvents;
use Drupal\static_export\Exporter\ExporterPluginInterface;
use Drupal\static_export\Exporter\ExporterReporterInterface;
use Drupal\static_export\File\FileCollectionWriter;
use Drupal\static_suite\Cli\CliCommandFactoryInterface;
use Drupal\static_suite\Lock\LockHelperInterface;
use Drupal\static_suite\Release\ReleaseInterface;
use Drupal\static_suite\Release\ReleaseManagerInterface;
use Drupal\static_suite\Release\Task\TaskInterface;
use Drupal\static_suite\StaticSuiteException;
use Drupal\static_suite\StaticSuiteUserException;
use Drupal\static_suite\Utility\BenchmarkTrait;
use Drupal\static_suite\Utility\StaticSuiteUtilsInterface;
use Drupal\static_suite\Utility\UniqueIdHelperInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Throwable;

/**
 * Base class for Static Builder plugins.
 */
abstract class StaticBuilderPluginBase extends ReleaseBasedConfigurableDrushAsyncTaskPluginBase implements StaticBuilderPluginInterface {

  use LoggerChannelTrait;
  use StringTranslationTrait;
  use BenchmarkTrait;

  /**
   * Unique identifier.
   *
   * @var string
   */
  protected $uniqueId;

  /**
   * Drupal config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * Drupal file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * Exporter service.
   *
   * @var \Drupal\static_export\Exporter\ExporterReporterInterface
   */
  protected $exporterReporter;

  /**
   * Static Suite utils.
   *
   * @var \Drupal\static_suite\Utility\StaticSuiteUtilsInterface
   */
  protected $staticSuiteUtils;

  /**
   * Unique ID helper.
   *
   * @var \Drupal\static_suite\Utility\UniqueIdHelperInterface
   */
  protected $uniqueIdHelper;

  /**
   * Release being built.
   *
   * @var \Drupal\static_suite\Release\Release
   */
  protected $release;

  /**
   * Release task.
   *
   * @var \Drupal\static_suite\Release\Task\Task
   */
  protected $releaseTask;

  /**
   * Array of values used to check if we must build.
   *
   * Used to avoid infinite loops.
   *
   * @var array
   */
  protected $mustBuildCheckValues = [];

  /**
   * Index number during the build loop.
   *
   * @var int
   */
  protected $buildIndex;

  /**
   * The lock helper from Static Suite.
   *
   * @var \Drupal\static_suite\Lock\LockHelperInterface
   */
  protected $lockHelper;

  /**
   * The lock system.
   *
   * @var \Drupal\Core\Lock\LockBackendInterface
   */
  protected $lock;

  /**
   * The stream wrapper manager.
   *
   * @var \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface
   */
  protected $streamWrapperManager;

  /**
   * The stream wrapper used by this builder.
   *
   * @var bool|\Drupal\Core\StreamWrapper\StreamWrapperInterface
   */
  protected $streamWrapper;

  /**
   * The data dir from static_export.
   *
   * @var string
   */
  protected $dataDir;

  /**
   * An array of messages to be logged.
   *
   * @var array
   */
  protected $logMessagesStack;

  /**
   * {@inheritdoc}
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   Drupal config service.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   The event dispatcher service.
   * @param \Drupal\Core\File\FileSystemInterface $fileSystem
   *   Drupal file system service.
   * @param \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface $streamWrapperManager
   *   The stream wrapper manager.
   * @param \Drupal\static_suite\Cli\CliCommandFactoryInterface $cliCommandFactory
   *   The CLI command factory.
   * @param \Drupal\static_suite\Lock\LockHelperInterface $lockHelper
   *   The lock helper from Static Suite.
   * @param \Drupal\static_suite\Release\ReleaseManagerInterface $releaseManager
   *   The release manager.
   * @param \Drupal\static_export\Exporter\ExporterReporterInterface $exporterReporter
   *   The exporter reporter service.
   * @param \Drupal\static_suite\Utility\StaticSuiteUtilsInterface $static_suite_utils
   *   Static Suite utils.
   * @param \Drupal\static_suite\Utility\UniqueIdHelperInterface $unique_id_helper
   *   Unique ID helper.
   *
   * @throws \Drupal\static_suite\StaticSuiteUserException
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    ConfigFactoryInterface $configFactory,
    EventDispatcherInterface $eventDispatcher,
    FileSystemInterface $fileSystem,
    StreamWrapperManagerInterface $streamWrapperManager,
    CliCommandFactoryInterface $cliCommandFactory,
    LockHelperInterface $lockHelper,
    ReleaseManagerInterface $releaseManager,
    ExporterReporterInterface $exporterReporter,
    StaticSuiteUtilsInterface $static_suite_utils,
    UniqueIdHelperInterface $unique_id_helper
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $cliCommandFactory, $releaseManager);
    $this->configFactory = $configFactory;
    $this->eventDispatcher = $eventDispatcher;
    $this->fileSystem = $fileSystem;
    $this->lockHelper = $lockHelper;
    $this->lock = $this->lockHelper->getLock();
    $this->streamWrapperManager = $streamWrapperManager;
    $this->setConfiguration($configuration);

    $scheme = $this->configFactory->get('static_export.settings')
      ->get('uri.scheme');
    $streamWrapper = $this->streamWrapperManager->getViaScheme($scheme);
    // Check if the selected stream wrapper is supported.
    if (!$streamWrapper) {
      throw new StaticSuiteUserException('No stream wrapper available for scheme "' . $scheme . '://"');
    }
    $this->streamWrapper = $streamWrapper;
    // Since this class is instantiated across multiple places (mainly to get
    // its release manager), do not check here if $this->streamWrapper is local
    // or remote, and do it inside init() method, which is the one called when
    // an actual build process is executed. Thus, if $this->streamWrapper is
    // remote, $this->dataDir becomes FALSE. That is not a problem until init()
    // is executed.
    $this->dataDir = $this->streamWrapper->realpath();
    $this->exporterReporter = $exporterReporter;
    $this->staticSuiteUtils = $static_suite_utils;
    $this->uniqueIdHelper = $unique_id_helper;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\static_suite\StaticSuiteUserException
   */
  public static function create(ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get("config.factory"),
      $container->get("event_dispatcher"),
      $container->get("file_system"),
      $container->get("stream_wrapper_manager"),
      $container->get("static_suite.cli_command_factory"),
      $container->get("static_suite.lock_helper"),
      $container->get("static_suite.release_manager"),
      $container->get("static_export.exporter_reporter"),
      $container->get("static_suite.utils"),
      $container->get("static_suite.unique_id_helper")
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getTaskId(): string {
    return self::TASK_ID . '-' . $this->pluginId;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\static_suite\StaticSuiteException
   */
  public function init(): void {
    if ($this->isLocal() && $this->streamWrapper::getType() !== StreamWrapperInterface::LOCAL_NORMAL) {
      throw new StaticSuiteUserException('The stream wrapper "' . $this->streamWrapper->getUri() . '" is remote and can not be used to build a site.');
    }
    parent::init();
  }

  /**
   * {@inheritdoc}
   */
  public function getCommand(): string {
    $commandOptions = [
      $this->pluginId,
      $this->configuration['run-mode'],
      '--lock-mode=' . $this->configuration['lock-mode'],
    ];
    if ($this->configuration['request-deploy']) {
      $commandOptions[] = '--request-deploy';
    }
    $commandOptions[] = $this->configuration['drush-options'];
    return self::DRUSH_ASYNC_COMMAND . ' ' . implode(' ', $commandOptions);
  }

  /**
   * {@inheritdoc}
   */
  public function getForkLogPath(): ?string {
    // Define a log file so we can get info about the forking process.
    $forkLog = $this->fileSystem->getTempDirectory() . '/static_build_fork.' . $this->configuration['run-mode'] . '.' . $this->pluginId;
    $forkLog .= $this->configuration['drush-options'] ? '.' . md5($this->configuration['drush-options']) : '';
    $forkLog .= '.log';
    return $forkLog;
  }

  /**
   * {@inheritdoc}
   */
  public function fork(): void {
    $this->dispatchEvent(StaticBuildEvents::ASYNC_PROCESS_FORK_START);
    parent::fork();
    $this->dispatchEvent(StaticBuildEvents::ASYNC_PROCESS_FORK_END);
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\static_suite\StaticSuiteException
   */
  public function run(): void {
    $this->dispatchEvent(StaticBuildEvents::CHAINED_STEP_START);
    $this->buildIndex = 1;
    $buildLockName = $this->pluginId . '--' . $this->configuration['run-mode-dir'] . '--build';
    try {
      $this->dispatchEvent(StaticBuildEvents::START);
      // First of all, create log dir.
      $this->createLogDir();

      $this->releaseManager->init($this->configuration['run-mode-dir']);

      $this->logMessage("**********************************************************");
      $this->logMessage("BUILD RUN STARTS");
      $this->logMessage("**********************************************************");
      $this->dispatchEvent(StaticBuildEvents::BUILD_RUN_START);
      // Unset console-output to avoid getting that object printed in logs.
      $configurationWithoutConsoleOutput = $this->configuration;
      unset($configurationWithoutConsoleOutput['console-output']);
      $this->logMessage("Config:\n" . print_r($configurationWithoutConsoleOutput, TRUE));

      while (TRUE) {
        // On each iteration, acquire a lock (the first time) or extend its
        // expiration (from the second time). In sites with high update rates,
        // where multiple editors are making changes at the same time, this
        // loop can be running for hours. Since Drupal semaphores have a
        // timeout to protect themselves against stale locks, we need to update
        // the lock we are using, extending its expiration on every loop.
        // Otherwise, our lock would be considered stale after some iterations
        // over this loop, and several builds would take place at the same time.
        if (!$this->lock->acquire($buildLockName, $this->configFactory->get('static_build.settings')
          ->get('semaphore_timeout'))) {
          $this->logMessage("Another build process is running. Aborting this build.");
          $this->logMessage("**********************************************************");
          $this->logMessage("BUILD RUN ABORTED");
          $this->logMessage("**********************************************************\n");
          return;
        }

        if ($this->buildIndex === 1) {
          $this->logMessage("[LOCK] Build lock acquired.");
        }
        else {
          $this->logMessage("[LOCK] Build lock expiration extended.");
        }
        $this->logMessage("No other build process running. Go ahead with this build.");

        // Check if we really need to build the site again on each iteration.
        // Lock data-dir to ensure next checks (mainly mustBuild()) operate on
        // data that is 100% accurate.
        $this->logMessage("[LOCK] Waiting to lock data dir...");
        if (!$this->lockHelper->acquireOrWait(ExporterPluginInterface::DATA_DIR_LOCK_NAME)) {
          throw new StaticSuiteException('Could not acquire lock on "' . $this->dataDir . '", timeout reached.');
        }
        $this->logMessage("[LOCK] Data dir lock successfully acquired.");

        // Discover "last locked executed unique id" and set it as our
        // Unique ID for this loop.
        $this->uniqueId = $this->getLastLockExecutedUniqueId();
        $this->logMessage("Discovered 'last locked executed unique id': " . $this->uniqueId);
        $this->logMessage("Using this UNIQUE ID for this loop #" . $this->buildIndex . ": " . $this->uniqueId);

        // Check if we need to run a build.
        if (!$this->mustBuild()) {
          $this->lock->release(ExporterPluginInterface::DATA_DIR_LOCK_NAME);
          $this->logMessage("[LOCK] Data dir lock released.");
          $this->logMessage("Breaking the loop...");
          break;
        }

        $this->logMessage("----------------------------------------------------------");
        $this->logMessage("[" . $this->buildIndex . "] BUILD LOOP #" . $this->buildIndex . " STARTS");
        $this->logMessage("----------------------------------------------------------");

        $this->dispatchEvent(StaticBuildEvents::BUILD_LOOP_START);

        $this->startBenchmark();

        // CREATE RELEASE DIR.
        $this->dispatchEvent(StaticBuildEvents::RELEASE_DIR_CREATION_START);
        $this->logMessage("[" . $this->buildIndex . "] Creating release dir...");
        $this->release = $this->releaseManager->create($this->uniqueId);
        $this->release->createReleaseDir();
        $this->releaseTask = $this->release->task($this->getTaskId());
        $this->releaseTask->setStarted();
        $this->logMessage("[" . $this->buildIndex . "] Release dir created at " . $this->release->getDir());
        $this->dispatchEvent(StaticBuildEvents::RELEASE_DIR_CREATION_END);

        // COPY DATA-DIR.
        $this->dispatchEvent(StaticBuildEvents::DATA_COPYING_START);
        if (!is_dir($this->configuration['build-dir']) && !$this->fileSystem->mkdir($this->configuration['build-dir'], 0777, TRUE)) {
          throw new StaticSuiteException(sprintf('Directory "%s" was not created', $this->configuration['build-dir']));
        }
        $this->logMessage("[" . $this->buildIndex . "] Copying data dir " . $this->dataDir . ' to ' . $this->configuration['build-dir']);
        if ($this->isLocal()) {
          $this->copyDataToBuildDir($this->dataDir, 'data', $this->getDataDirExcludedPaths());
        }
        else {
          $this->copyDataToBuildDir($this->dataDir, 'data', $this->getDataDirExcludedPaths(), TRUE);
        }
        $this->logMessage("[" . $this->buildIndex . "] Data dir copied.");
        $this->dispatchEvent(StaticBuildEvents::DATA_COPYING_END);

        // RELEASE DATA-DIR LOCK.
        $this->lock->release(ExporterPluginInterface::DATA_DIR_LOCK_NAME);
        $this->logMessage("[" . $this->buildIndex . "] [LOCK] Data dir lock released. Don't panic if you see a concurrent build trying to start and giving up.");

        // EXECUTE PREBUILD.
        $this->dispatchEvent(StaticBuildEvents::PRE_BUILD_START);
        $this->logMessage("[" . $this->buildIndex . "] Executing preBuild for " . $this->release->getDir());
        $this->preBuild();
        $this->logMessage("[" . $this->buildIndex . "] Prebuild executed.");
        $this->dispatchEvent(StaticBuildEvents::PRE_BUILD_END);

        // BUILD.
        $this->dispatchEvent(StaticBuildEvents::BUILD_START);
        $this->logMessage("[" . $this->buildIndex . "] Executing build in " . $this->configuration['run-mode'] . " mode for " . $this->release->getDir());
        $this->build();
        $this->logMessage("[" . $this->buildIndex . "] build executed.");
        $this->dispatchEvent(StaticBuildEvents::BUILD_END);

        // EXECUTE POST BUILD.
        $this->dispatchEvent(StaticBuildEvents::POST_BUILD_START);
        $this->logMessage("[" . $this->buildIndex . "] Executing postBuild for " . $this->release->getDir());
        $this->postBuild();
        $this->logMessage("[" . $this->buildIndex . "] PostBuild executed.");
        $this->dispatchEvent(StaticBuildEvents::POST_BUILD_END);

        // Mark this release as successfully built.
        $this->releaseTask->setDone();

        // PUBLISH RELEASE.
        $this->dispatchEvent(StaticBuildEvents::PUBLISH_RELEASE_START);
        $this->logMessage("[" . $this->buildIndex . "] Trying to publish release " . $this->release->uniqueId());
        $this->setCurrentSymlink();
        $this->logMessage("[" . $this->buildIndex . "] Release published.");
        $this->dispatchEvent(StaticBuildEvents::PUBLISH_RELEASE_END);

        // DELETE OLD RELEASES.
        $this->dispatchEvent(StaticBuildEvents::OLD_RELEASES_DELETION_START);
        $this->logMessage("[" . $this->buildIndex . "] Deleting old releases.");
        $this->releaseManager->deleteOldReleases();
        $this->logMessage("[" . $this->buildIndex . "] Old releases deleted.");
        $this->dispatchEvent(StaticBuildEvents::OLD_RELEASES_DELETION_END);

        $this->logMessage("----------------------------------------------------------");
        $this->logMessage("[" . $this->buildIndex . "] BUILD LOOP #" . $this->buildIndex . " ENDS: " . $this->getBenchmark() . ' secs.');
        $this->logMessage("----------------------------------------------------------");

        $this->dispatchEvent(StaticBuildEvents::BUILD_LOOP_END);

        $this->buildIndex++;
      }

      // RELEASE BUILD LOCK.
      $this->lock->release($buildLockName);
      $this->logMessage("[LOCK] Build lock released.");
      $this->logMessage("**********************************************************");
      $this->logMessage("BUILD RUN ENDS");
      $this->logMessage("**********************************************************\n");
      $this->dispatchEvent(StaticBuildEvents::BUILD_RUN_END);

      $this->dispatchEvent(StaticBuildEvents::END);
    }
    catch (Throwable $e) {
      // This is Throwable to capture any kind of exception and ensure
      // that any pending work is done.
      $this->logMessage("Exception thrown. Cleaning up...");
      $this->logMessage($e->getMessage() . "\n" . $e->getTraceAsString());

      // If this exception comes from Static Build, log an error.
      if ($e instanceof StaticSuiteException) {
        $message = $e->getMessage() . " Please, review " . $this->configuration['revisions-log-file'];
        $this->getLogger('static_build')->error($message);
      }

      // Mark release as failed.
      if ($this->releaseTask && $this->releaseTask->isStarted()) {
        $this->releaseTask->setFailed();
      }

      // Double ensure that a current release is present.
      if (!$this->releaseManager->getCurrentUniqueId()) {
        $this->logMessage("[" . $this->buildIndex . "] No current release. This is a major issue. Trying to fix it.");
        // Find the latest done release and do a rollback.
        $lastDoneRelease = $this->releaseManager->getTaskSupervisor()
          ->getLastReleaseByTaskStatus($this->getTaskId(), TaskInterface::DONE);
        if ($lastDoneRelease && $lastDoneRelease->uniqueId()) {
          $lastTryResult = $this->releaseManager->publish($lastDoneRelease->uniqueId(), $this->getTaskId());
          if ($lastTryResult === TRUE) {
            $this->logMessage("LAST ROLLBACK FOR RELEASE " . $lastDoneRelease->uniqueId() . " SUCCESSFUL!");
          }
          else {
            // OH NO!!! WE CANNOT FIX THIS!!!
            $this->logMessage("LAST ROLLBACK FOR RELEASE " . $lastDoneRelease->uniqueId() . " FAILED!");
            $this->logMessage("[" . $this->buildIndex . "] ######################################################");
            $this->logMessage("[" . $this->buildIndex . "] ################### SITE IS BROKEN ###################");
            $this->logMessage("[" . $this->buildIndex . "] ######################################################");

          }
        }
      }

      // Delete old releases to protect disk space if builds are falling and
      // they are been running continuously.
      $this->logMessage("[" . $this->buildIndex . "] Deleting old releases.");
      $this->releaseManager->deleteOldReleases();
      $this->logMessage("[" . $this->buildIndex . "] Old releases deleted.");

      // Release any pending lock.
      $this->lock->release(ExporterPluginInterface::DATA_DIR_LOCK_NAME);
      $this->logMessage("[LOCK] Data lock released.");
      $this->lock->release($buildLockName);
      $this->logMessage("[LOCK] Build lock released.");

      $this->dispatchEvent(StaticBuildEvents::END);
      throw new StaticSuiteException($e);
    }
    finally {
      $this->lock->releaseAll();
    }

    $this->dispatchEvent(StaticBuildEvents::CHAINED_STEP_END);
  }

  /**
   * {@inheritdoc}
   */
  public function preBuild(): void {
    // By default, do nothing.
  }

  /**
   * Get an array of paths to exclude from data copy.
   *
   * Basically, it returns the path of the work_dir, that shouldn't be copied.
   *
   * @return array
   *   Array of paths.
   *
   * @todo remove this method once work dir gets refactored and, ideally,
   *   removed, or when it can be moved outside the data directory.
   */
  public function getDataDirExcludedPaths(): array {
    $excludedPaths = [];
    $workDir = $this->configFactory->get('static_export.settings')
      ->get('work_dir');
    if (strpos($workDir, $this->dataDir) !== FALSE) {
      $excludedPaths[] = str_replace($this->dataDir . '/', '', $workDir);
    }
    return $excludedPaths;
  }

  /**
   * {@inheritdoc}
   */
  public function postBuild(): void {
    // By default, do nothing.
  }

  /**
   * Set $this->release as current.
   *
   * Tries to leave the site in good condition under any circumstances, making
   * the current symlink point to the best release found.
   *
   * @return bool
   *   True on success.
   *
   * @throws \Drupal\static_suite\StaticSuiteException
   *
   * @todo Move this method to a "BuildDirManager" or similar
   */
  public function setCurrentSymlink(): bool {
    // Get current id in case we need to execute a rollback.
    $currentReleaseUniqueId = $this->releaseManager->getCurrentUniqueId();
    $this->logMessage("[" . $this->buildIndex . "] Publishing release *** " . $this->release->uniqueId() . " *** . Previous current was $currentReleaseUniqueId.");
    $result = $this->releaseManager->publish($this->release->uniqueId(), $this->getTaskId());
    if ($result === TRUE) {
      $this->logMessage("[" . $this->buildIndex . "] RELEASE PUBLISHED!");
    }
    else {
      $this->logMessage("[" . $this->buildIndex . "] ****** RELEASE NOT PUBLISHED ******");
      $rollbackResult = FALSE;
      // Try with $currentReleaseUniqueId if it's not null.
      if ($currentReleaseUniqueId) {
        $this->logMessage("[" . $this->buildIndex . "] Rolling back to $currentReleaseUniqueId");
        $rollbackResult = $this->releaseManager->publish($currentReleaseUniqueId, $this->getTaskId());
      }
      else {
        $this->logMessage("[" . $this->buildIndex . "] No previous current unique id.");
      }
      if ($rollbackResult === TRUE) {
        $this->logMessage("[" . $this->buildIndex . "] ROLLBACK FOR RELEASE $currentReleaseUniqueId SUCCESSFUL!");
      }
      else {
        $this->logMessage("[" . $this->buildIndex . "] ROLLBACK FOR RELEASE $currentReleaseUniqueId FAILED!");
        $this->logMessage("[" . $this->buildIndex . "] Last try. Ensure that there is a current release.");
        if ($this->releaseManager->getCurrentUniqueId()) {
          $this->logMessage("[" . $this->buildIndex . "] There is a current release for " . $this->releaseManager->getCurrentUniqueId() . '. No need to fix anything.');
        }
        else {
          $this->logMessage("[" . $this->buildIndex . "] No current release. This is a major issue. Trying to fix it.");
          // As a last try, find the latest done release and do a rollback.
          $lastDoneRelease = $this->releaseManager->getTaskSupervisor()
            ->getLastReleaseByTaskStatus($this->getTaskId(), TaskInterface::DONE);
          $lastTryResult = FALSE;
          if ($lastDoneRelease && $lastDoneRelease->uniqueId()) {
            $lastTryResult = $this->releaseManager->publish($lastDoneRelease->uniqueId(), $this->getTaskId());
          }
          if ($lastTryResult === TRUE) {
            $this->logMessage("[" . $this->buildIndex . "] ROLLBACK FOR RELEASE " . $lastDoneRelease->uniqueId() . " SUCCESSFUL!");
          }
          else {
            // OH NO!!! WE CANNOT FIX THIS!!!
            $this->logMessage("[" . $this->buildIndex . "] ROLLBACK FOR RELEASE " . $lastDoneRelease->uniqueId() . " FAILED!");
            $this->logMessage("[" . $this->buildIndex . "] ######################################################");
            $this->logMessage("[" . $this->buildIndex . "] ################### SITE IS BROKEN ###################");
            $this->logMessage("[" . $this->buildIndex . "] ######################################################");

            // Throw this error.
            throw new StaticSuiteException("SITE IS BROKEN. NO CURRENT SYMLINK. Unable to publish release " . $this->release->uniqueId());
          }
        }
      }
    }
    return TRUE;
  }

  /**
   * Check if we really need to build the site again.
   *
   * It honors "last locked executed unique id" from static_export, since that's
   * the value that holds when data was last changed. If current release differs
   * from that value, we must build the site again, except when changed files
   * do not match any of the regexps defined on setBuildTriggerRegexps()
   *
   * @return bool
   *   True if we must trigger a build.
   */
  public function mustBuild(): bool {
    $this->logMessage('Checking if we must run a build with the following info:');
    $this->logMessage('$this->uniqueId=' . $this->uniqueId);

    // Check current release unique id.
    $currentRelease = $this->releaseManager->getCurrentRelease();
    $currentReleaseUniqueId = $currentRelease ? $currentRelease->uniqueId() : NULL;
    $this->logMessage('$currentReleaseUniqueId=' . $currentReleaseUniqueId);
    if ($this->uniqueId === $currentReleaseUniqueId) {
      $this->logMessage('$this->uniqueId (' . $this->uniqueId . ") is the same as currentReleaseUniqueId ($currentReleaseUniqueId).");
      $this->logMessage('*** NO NEED TO RUN A BUILD ***');
      return FALSE;
    }

    // Check if $this->uniqueId has a release dir. That means it's a failed
    // build so we need to abort to avoid errors and infinite loops.
    $onGoingReleaseExists = $this->releaseManager->exists($this->uniqueId);
    $this->logMessage('$onGoingReleaseExists=' . $onGoingReleaseExists);
    if ($onGoingReleaseExists) {
      $this->logMessage('Ongoing release ' . $this->uniqueId . " exists.");
      $this->logMessage('*** NO NEED TO RUN A BUILD ***');
      return FALSE;
    }

    // Compare current values with previous ones to avoid infinite loops.
    if (
      isset($this->mustBuildCheckValues['loopUniqueId']) &&
      $this->mustBuildCheckValues['loopUniqueId'] === $this->uniqueId &&
      $this->mustBuildCheckValues['currentReleaseUniqueId'] === $currentReleaseUniqueId &&
      $this->mustBuildCheckValues['onGoingReleaseExists'] === $onGoingReleaseExists
    ) {
      $this->logMessage('Infinite loop detected. Same values as before.');
      $this->logMessage('*** NO NEED TO RUN A BUILD ***');
      return FALSE;
    }

    // Check if any of the files that have changed since last build
    // must trigger a build.
    $buildTriggerRegexps = $this->configuration['build-trigger-regexp-list'];
    $this->logMessage('RegExp List to match against changed files: ' . implode("\n", $buildTriggerRegexps));
    if ($currentReleaseUniqueId && is_array($buildTriggerRegexps) && count($buildTriggerRegexps)) {
      $changedFiles = $this->exporterReporter->getChangedFilesAfter($currentReleaseUniqueId);
      $changedFilePaths = [];
      if ($changedFiles) {
        foreach ($changedFiles as $changedFile) {
          $changedFilePaths[] = $changedFile['absolute-path'];
        }
        $this->logMessage('Changed files since last build: ' . implode("\n", $changedFilePaths));
      }
      else {
        $this->logMessage('No changed files since last build.');
      }
      if ($this->staticSuiteUtils->isAnyItemMatchingRegexpList($changedFilePaths, $buildTriggerRegexps)) {
        $this->logMessage('Some changed files match RegExp list.');
      }
      else {
        $this->logMessage('No changed files match RegExp list.');
        $this->logMessage('*** NO NEED TO RUN A BUILD ***');
        return FALSE;
      }
    }
    else {
      $this->logMessage("No 'build-trigger-regexp-list' config found. Check not executed.");
    }

    // Save current values to compare them on next iteration.
    $this->mustBuildCheckValues = [
      'loopUniqueId' => $this->uniqueId,
      'currentReleaseUniqueId' => $currentReleaseUniqueId,
      'onGoingReleaseExists' => $onGoingReleaseExists,
    ];

    $this->logMessage('All checks passed.');
    $this->logMessage('*** MUST RUN A BUILD ***');
    return TRUE;
  }

  /**
   * Create log dir.
   *
   * This dir must be 777 to allow other processes (like deploy) to log
   * its operations.
   *
   * @return bool
   *   True on success.
   *
   * @throws \Drupal\static_suite\StaticSuiteException
   */
  public function createLogDir(): bool {
    if (!is_dir($this->configuration['log-dir'])) {
      $mkdirResult = $this->fileSystem->mkdir($this->configuration['log-dir'], 0777, TRUE);
      if ($mkdirResult === FALSE) {
        throw new StaticSuiteException("Unable to create log dir " . $this->configuration['log-dir']);
      }
    }
    return TRUE;
  }

  /**
   * Get the last lock executed unique id.
   *
   * It takes into account the current lock mode, because there is a
   * different lock file for each run mode.
   *
   * @return string
   *   The last unique id which made changes to data-dir.
   * @todo Move this method to a "WorkDirManager" or similar
   */
  public function getLastLockExecutedUniqueId(): string {
    $workDir = $this->configFactory->get('static_export.settings')
      ->get('work_dir');
    if ($this->configuration['lock-mode'] === self::LOCK_MODE_PREVIEW) {
      $filePath = $workDir . "/" . FileCollectionWriter::LAST_LOCK_EXECUTED_PREVIEW_UNIQUE_ID_FILE;
    }
    else {
      $filePath = $workDir . "/" . FileCollectionWriter::LAST_LOCK_EXECUTED_LIVE_UNIQUE_ID_FILE;
    }
    clearstatcache(TRUE, $filePath);
    $lastLockExecutedUniqueId = @file_get_contents($filePath);
    if ($lastLockExecutedUniqueId === FALSE) {
      $lastLockExecutedUniqueId = $this->uniqueIdHelper->getDefaultUniqueId();
    }
    return $lastLockExecutedUniqueId;
  }

  /**
   * {@inheritdoc}
   */
  public function logMessage(string $message, bool $mustFormat = TRUE) {
    try {
      if (!empty($message)) {
        if ($mustFormat) {
          $timestamp = $this->staticSuiteUtils->getFormattedMicroDate('Y-m-d H:i:s.u');
          $formattedMessage = getmypid() . " [$timestamp] [$this->uniqueId] $message";
        }
        else {
          $formattedMessage = $message;
        }
        $logFileRevisions = $this->configuration['revisions-log-file'];
        if (is_writable($this->fileSystem->dirname($logFileRevisions))) {
          @file_put_contents($logFileRevisions, "$formattedMessage\n", LOCK_EX);
        }

        // build-log-file inside a release is present at a later moment, so
        // save its logs and write them when log is ready to be used.
        if ($this->releaseTask) {
          $logFileBuild = $this->releaseTask->getLogFilePath();
          if (is_writable($this->fileSystem->dirname($logFileBuild))) {
            $messageToLog = $formattedMessage;
            if (isset($this->logMessagesStack['build']) && is_array($this->logMessagesStack['build']) && count($this->logMessagesStack['build'])) {
              $messageToLog = implode("\n", $this->logMessagesStack['build']) . $formattedMessage;
              unset($this->logMessagesStack['build']);
            }
            @file_put_contents($logFileBuild, "$messageToLog\n", FILE_APPEND | LOCK_EX);
          }
        }
        else {
          $this->logMessagesStack['build'][] = $formattedMessage;
        }

        if (!empty($this->configuration['console-output']) && is_object($this->configuration['console-output'])) {
          $this->configuration['console-output']->writeln($formattedMessage);
        }
      }
    }
    catch (Throwable $e) {
      // Do nothing.
    }
  }

  /**
   * {@inheritdoc}
   *
   * Plugins can define their own configuration by overriding this method.
   */
  public function defaultConfiguration(): array {
    $config = $this->configFactory->get('static_build.settings');

    $env = [];
    $configEnv = $config->get('env');
    if (is_array($configEnv)) {
      foreach ($configEnv as $configEnvLine) {
        [$variable, $value] = explode("=", $configEnvLine, 2);
        $env[$variable] = $value;
      }
    }

    $defaultConfiguration = [
      'id' => $this->pluginId,
      'base-dir' => $config->get('base_dir') . '/' . $this->pluginId,
      // "number_of_releases_to_keep" is not used by a builder at this
      // moment, so it cannot be overridden by other plugins.
      'env' => $env,
      'drush-options' => $config->get('drush_options'),
      'sync' => !empty($config->get('local.sync')),
      'request-deploy' => TRUE,
    ];

    // Merge parent configuration.
    return NestedArray::mergeDeep(
      parent::defaultConfiguration(),
      $defaultConfiguration
    );
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\static_suite\StaticSuiteUserException
   */
  public function setConfiguration(array $configuration) {
    $config = $this->configFactory->get('static_build.settings');

    // Check for a proper run-mode.
    if (!in_array($configuration['run-mode'], [
      self::RUN_MODE_PREVIEW,
      self::RUN_MODE_LIVE,
    ], TRUE)) {
      throw new StaticSuiteUserException('Run mode "' . $configuration['run-mode'] . '" is not supported. Possible values: ' . self::RUN_MODE_PREVIEW . ' and ' . self::RUN_MODE_LIVE);
    }

    // Ensure that lock-mode defaults to live if no one provided.
    // This mainly affects preview builds, because they must run when something
    // published has changed. Honoring the preview lock would trigger a preview
    // build only when unpublished data has changed.
    // This behaviour can be overridden by passing an explicit lock-mode.
    if (empty($configuration['lock-mode'])) {
      $configuration['lock-mode'] = self::LOCK_MODE_LIVE;
    }

    // Check for a proper lock-mode.
    if (!in_array($configuration['lock-mode'], [
      self::LOCK_MODE_PREVIEW,
      self::LOCK_MODE_LIVE,
    ], TRUE)) {
      throw new StaticSuiteUserException('Lock mode "' . $configuration['lock-mode'] . '" is not supported. Possible values: ' . self::LOCK_MODE_PREVIEW . ' and ' . self::LOCK_MODE_LIVE);
    }

    // Check for a proper lock-mode.
    if (
      $this->isCloud() &&
      $configuration['lock-mode'] === self::LOCK_MODE_PREVIEW
    ) {
      throw new StaticSuiteUserException('Lock mode "' . $configuration['lock-mode'] . '" is not available for cloud builders. Possible values: ' . self::LOCK_MODE_LIVE);
    }

    // Set other values.
    $configuration['build-trigger-regexp-list'] = $config->get('build_trigger_regexp_list_' . $configuration['run-mode']) ?: [];

    // Merge config and get a new one.
    $configuration = NestedArray::mergeDeep(
      $this->defaultConfiguration(),
      $configuration
    );

    // Set other config values that shouldn't be overridden.
    $configuration['base-dir'] = $config->get('base_dir') . '/' . $this->pluginId;
    $configuration['run-mode-dir'] = $configuration['base-dir'] . '/' . $configuration['run-mode'];
    $configuration['build-dir'] = $configuration['run-mode-dir'] . '/' . self::BUILD_DIR;
    $configuration['log-dir'] = $configuration['run-mode-dir'] . '/' . self::LOG_DIR;
    $configuration['revisions-log-file'] = $configuration['log-dir'] . '/' . self::LAST_BUILD_LOG_NAME;

    // Finally, set configuration.
    $this->configuration = $configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function isLocal(): bool {
    return isset($this->pluginDefinition['host']) && $this->pluginDefinition['host'] === self::HOST_MODE_LOCAL;
  }

  /**
   * {@inheritdoc}
   */
  public function isCloud(): bool {
    return isset($this->pluginDefinition['host']) && $this->pluginDefinition['host'] === self::HOST_MODE_CLOUD;
  }

  /**
   * Delete a dir/file inside the build dir.
   *
   * @param string $localPath
   *   A local path inside the build dir.
   *
   * @return bool
   *   True on success.
   *
   * @throws \Drupal\static_suite\StaticSuiteException
   * @todo Move this method to a BuildDirManager or similar.
   */
  public function deleteInsideBuildDir(string $localPath): bool {
    $pathToDelete = $this->configuration['build-dir'] . '/' . $localPath;
    exec("rm -Rf $pathToDelete", $output, $returnValue);
    if ($returnValue !== 0) {
      throw new StaticSuiteException("Unable to delete $pathToDelete:\n" . implode("\n", $output));
    }
    return TRUE;
  }

  /**
   * Copy dirs and files to a dir inside the build dir.
   *
   * @param string $source
   *   It can be external or local. If external, it must be an absolute path.
   * @param string $localDestination
   *   A path relative to release directory.
   * @param array $excludedPaths
   *   An array of paths to be excluded from the copy.
   * @param bool $createTar
   *   Flag to create a .tar file with copied data.
   *
   * @return bool
   *   True on success.
   *
   * @throws \Drupal\static_suite\StaticSuiteException
   * @todo Move this method to a BuildDirManager or similar.
   */
  public function copyDataToBuildDir(string $source, string $localDestination, array $excludedPaths, bool $createTar = FALSE): bool {
    clearstatcache(TRUE, $source);
    if (!is_readable($source)) {
      throw new StaticSuiteException("Source $source not readable.");
    }

    if (strpos($localDestination, "/..") !== FALSE || strpos($localDestination, "../") !== FALSE) {
      throw new StaticSuiteException("Wrong parameter localDestination ($localDestination) must be a path inside " . $this->configuration['build-dir']);
    }

    $destination = $this->configuration['build-dir'] . '/' . $localDestination;
    $excludeOptions = '';
    foreach ($excludedPaths as $excludedPath) {
      $excludeOptions .= "--exclude='$excludedPath' ";
    }
    clearstatcache(TRUE, $source);
    if (is_file($source) || is_link($source)) {
      $command = "cp -a $source $destination";
    }
    else {
      $command = "rsync -av $excludeOptions $source/ $destination --delete";
    }
    exec($command, $output, $returnValue);
    if ($returnValue !== 0) {
      throw new StaticSuiteException("Unable to copy $source to $localDestination:\n" . implode("\n", $output));
    }
    // Save unique id of this data.
    file_put_contents($destination . '/' . ReleaseInterface::UNIQUE_ID_FILE_NAME, $this->uniqueId);

    if ($createTar) {
      $tarFile = '../' . $localDestination . '.tar';
      $command = 'cd ' . $destination . ' && tar -cf ' . $tarFile . ' *';
      exec($command, $output, $returnValue);
      if ($returnValue !== 0) {
        throw new StaticSuiteException("Unable to create tar file ($tarFile) from $destination:\n" . implode("\n", $output));
      }
    }

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    $dependencies = [];
    $definition = $this->getPluginDefinition();
    $dependencies['module'][] = $definition['provider'];
    return $dependencies;
  }

  /**
   * Dispatch StaticBuildEvent.
   *
   * @param string $eventName
   *   The name of the event.
   * @param array $data
   *   Data for the event.
   *
   * @return \Drupal\static_build\Event\StaticBuildEvent
   *   The event.
   */
  public function dispatchEvent(string $eventName, array $data = []): StaticBuildEvent {
    $event = new StaticBuildEvent($this);
    $event->setData($data);

    // Dispatch the event.
    $this->logMessage("EVENT DISPATCH '$eventName' TRIGGERED");
    $this->eventDispatcher->dispatch($event, $eventName);
    $this->logMessage("EVENT DISPATCH '$eventName' DONE");

    // Return the event.
    return $event;
  }

}
