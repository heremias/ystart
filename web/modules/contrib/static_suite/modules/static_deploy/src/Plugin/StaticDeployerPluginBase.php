<?php

namespace Drupal\static_deploy\Plugin;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\static_build\Plugin\ReleaseBasedConfigurableDrushAsyncTaskPluginBase;
use Drupal\static_build\Plugin\StaticBuilderPluginInterface;
use Drupal\static_build\Plugin\StaticBuilderPluginManagerInterface;
use Drupal\static_deploy\Event\StaticDeployEvent;
use Drupal\static_deploy\Event\StaticDeployEvents;
use Drupal\static_suite\Cli\CliCommandFactoryInterface;
use Drupal\static_suite\Lock\LockHelperInterface;
use Drupal\static_suite\Release\ReleaseManager;
use Drupal\static_suite\StaticSuiteException;
use Drupal\static_suite\StaticSuiteUserException;
use Drupal\static_suite\Utility\BenchmarkTrait;
use Drupal\static_suite\Utility\StaticSuiteUtilsInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Throwable;

/**
 * Base class for Static deployer plugins.
 */
abstract class StaticDeployerPluginBase extends ReleaseBasedConfigurableDrushAsyncTaskPluginBase implements StaticDeployerPluginInterface {

  use LoggerChannelTrait;
  use BenchmarkTrait;

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
   * Static Suite utils.
   *
   * @var \Drupal\static_suite\Utility\StaticSuiteUtilsInterface
   */
  protected $staticSuiteUtils;

  /**
   * An array of messages to be logged.
   *
   * @var array
   */
  protected $logMessagesStack = [];

  /**
   * A flag to enable logging at a later moment, when all check are passed.
   *
   * @var bool
   */
  protected $startLogging = FALSE;

  /**
   * Current release.
   *
   * @var \Drupal\static_suite\Release\ReleaseInterface
   */
  protected $currentRelease;

  /**
   * Current release task.
   *
   * @var \Drupal\static_suite\Release\Task\Task
   */
  protected $releaseTask;

  /**
   * The static builder manager.
   *
   * @var \Drupal\static_build\Plugin\StaticBuilderPluginManagerInterface
   */
  protected $staticBuilderPluginManager;

  /**
   * The static builder that this deployer is using.
   *
   * @var \Drupal\static_build\Plugin\StaticBuilderPluginInterface
   */
  protected $staticBuilder;

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
   * @param \Drupal\static_suite\Cli\CliCommandFactoryInterface $cliCommandFactory
   *   The CLI command factory.
   * @param \Drupal\static_suite\Lock\LockHelperInterface $lockHelper
   *   The lock helper from Static Suite.
   * @param \Drupal\static_build\Plugin\StaticBuilderPluginManagerInterface $static_builder_manager
   *   The static builder plugin manager.
   * @param \Drupal\static_suite\Release\ReleaseManager $releaseManager
   *   The release manager service.
   * @param \Drupal\static_suite\Utility\StaticSuiteUtilsInterface $static_suite_utils
   *   Static Suite utils.
   *
   * @throws \Drupal\static_suite\StaticSuiteUserException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    ConfigFactoryInterface $configFactory,
    EventDispatcherInterface $eventDispatcher,
    FileSystemInterface $fileSystem,
    CliCommandFactoryInterface $cliCommandFactory,
    LockHelperInterface $lockHelper,
    StaticBuilderPluginManagerInterface $staticBuilderPluginManager,
    ReleaseManager $releaseManager,
    StaticSuiteUtilsInterface $staticSuiteUtils
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $cliCommandFactory, $releaseManager);
    $this->configFactory = $configFactory;
    $this->eventDispatcher = $eventDispatcher;
    $this->fileSystem = $fileSystem;
    $this->lockHelper = $lockHelper;
    $this->lock = $this->lockHelper->getLock();
    $this->staticBuilderPluginManager = $staticBuilderPluginManager;
    $this->staticSuiteUtils = $staticSuiteUtils;
    $this->setConfiguration($configuration);
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\static_suite\StaticSuiteUserException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
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
      $container->get("static_suite.cli_command_factory"),
      $container->get("static_suite.lock_helper"),
      $container->get("plugin.manager.static_builder"),
      $container->get("static_suite.release_manager"),
      $container->get("static_suite.utils")
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
   */
  public function getCommand(): string {
    $commandOptions = [
      $this->configuration['builder-id'],
      $this->pluginId,
    ];
    if ($this->configuration['force']) {
      $commandOptions[] = '--force';
    }
    $commandOptions[] = $this->configuration['drush-options'];
    return self::DRUSH_ASYNC_COMMAND . ' ' . implode(' ', $commandOptions);
  }

  /**
   * {@inheritdoc}
   */
  public function getForkLogPath(): ?string {
    // Define a log file so we can get info about the forking process.
    $forkLog = $this->fileSystem->getTempDirectory() . '/static_deploy_fork.' . $this->pluginId;
    $forkLog .= $this->configuration['drush-options'] ? '.' . md5($this->configuration['drush-options']) : '';
    $forkLog .= '.log';
    return $forkLog;
  }

  /**
   * {@inheritdoc}
   */
  public function fork(): void {
    $this->dispatchEvent(StaticDeployEvents::ASYNC_PROCESS_FORK_START);
    parent::fork();
    $this->dispatchEvent(StaticDeployEvents::ASYNC_PROCESS_FORK_END);
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\static_suite\StaticSuiteException
   */
  public function run(): void {
    $deployLockName = $this->configuration['run-mode-dir'] . '--deploy';
    $this->startBenchmark();

    $this->logMessage('**********************************************************');
    $this->logMessage('DEPLOY RUN STARTS');
    $this->logMessage('**********************************************************');

    if (!$this->lock->acquire($deployLockName, $this->configFactory->get('static_deploy.settings')
      ->get('semaphore_timeout'))) {
      $this->logMessage('Another deploy process is running. Aborting this one.');
      $this->logMessage("**********************************************************");
      $this->logMessage("DEPLOY RUN ABORTED");
      $this->logMessage("**********************************************************\n");
      return;
    }

    $this->dispatchEvent(StaticDeployEvents::CHAINED_STEP_START);

    try {
      $this->dispatchEvent(StaticDeployEvents::START);
      $this->releaseManager->init($this->configuration['run-mode-dir']);

      $this->logMessage('No other deploy process running. Go ahead with this one.');
      $this->logMessage('Deploy lock acquired.');

      // Discover current release.
      $this->currentRelease = $this->releaseManager->getCurrentRelease();
      $this->releaseTask = $this->currentRelease->task($this->getTaskId());
      $this->logMessage('Discovered current release: ' . $this->currentRelease->getDir());

      // Check that current release hasn't been deployed.
      if (!$this->configuration['force'] && $this->releaseTask->isDone()) {
        // We simply return without throwing. This is not an error.
        $this->logMessage('Release ' . $this->currentRelease->getDir() . ' is already deployed. Aborting.');
        return;
      }

      // Check that current release is built.
      if (!$this->currentRelease->task($this->staticBuilder->getTaskId())
        ->isDone()) {
        throw new StaticSuiteException('Release ' . $this->currentRelease->getDir() . " is not done. This a major issue that shouldn't happen. Aborting.");
      }

      // To avoid unnecessary logging, we only log when all checks are OK
      // and a real deployment is triggered.
      $this->startLogging = TRUE;
      $this->truncateLog();

      // Set current release as started.
      $this->logMessage('Release marked as started.');
      $this->releaseTask->setStarted();

      // Execute deploy operation from plugin.
      $this->dispatchEvent(StaticDeployEvents::DEPLOY_START);
      $this->deploy();
      $this->dispatchEvent(StaticDeployEvents::DEPLOY_END);

      // Set current release as deployed.
      $this->logMessage('Release marked as deployed.');
      $this->releaseTask->setDone();

      // RELEASE DEPLOY LOCK.
      $this->lock->release($deployLockName);
      $this->logMessage('Deploy lock released.');

      $this->logMessage('Elapsed time: ' . $this->getBenchmark() . 'sec.');

      $this->logMessage('**********************************************************');
      $this->logMessage('DEPLOY RUN ENDS');
      $this->logMessage('**********************************************************');

      $this->dispatchEvent(StaticDeployEvents::END);
    }
    catch (Throwable $e) {
      // This is Throwable to capture any kind of exception and ensure
      // that any pending work is done.
      $this->logMessage('Exception thrown. Cleaning up...');
      $this->logMessage($e->getMessage() . "\n" . $e->getTraceAsString());

      $this->dispatchEvent(StaticDeployEvents::ROLLBACK_START);
      $this->rollback();
      $this->dispatchEvent(StaticDeployEvents::ROLLBACK_END);

      // If this exception comes from Static Build, log an error.
      if ($e instanceof StaticSuiteException) {
        $message = $e->getMessage() . ' Please, review log files.';
        $this->getLogger('static_deploy')->error($message);
      }

      // Mark release as failed.
      if ($this->releaseTask && $this->releaseTask->isStarted()) {
        $this->releaseTask->setFailed();
      }

      // Release any pending lock.
      $this->lock->release($deployLockName);
      $this->logMessage('Deploy lock released.');

      throw new StaticSuiteException($e);
    }
    finally {
      $this->lock->releaseAll();
    }

    $this->dispatchEvent(StaticDeployEvents::CHAINED_STEP_END);
  }

  /**
   * Truncate the log message.
   *
   * Deployments can be executed several times over the same release, so its log
   * needs to be emptied everytime the deployer runs.
   */
  protected function truncateLog(): void {
    try {
      $logFilePath = $this->releaseTask?->getLogFilePath();
      if ($logFilePath && is_writable($this->fileSystem->dirname($logFilePath))) {
        @file_put_contents($logFilePath, '', LOCK_EX);
      }
    }
    catch (Throwable $e) {
      echo $e->getMessage();
    }
  }

  /**
   * Logs a message.
   *
   * Don't throw any error. It's essential to ensure proper recovery on
   * try-catch blocks.
   */
  protected function logMessage(string $message): void {
    try {
      if (!empty($message)) {
        $timestamp = $this->staticSuiteUtils->getFormattedMicroDate('Y-m-d H:i:s.u');
        $uniqueIdStamp = $this->currentRelease ? '[' . $this->currentRelease->uniqueId() . '] ' : '';
        $formattedMessage = getmypid() . ' [' . $timestamp . '] ' . $uniqueIdStamp . '[' . $this->pluginId . '] ' . $message;

        // To avoid unnecessary logging, we only log when all checks are OK
        // and a real deployment happens.
        $logFilePath = $this->releaseTask?->getLogFilePath();
        if ($this->startLogging && $logFilePath && is_writable($this->fileSystem->dirname($logFilePath))) {
          $messageToLog = $formattedMessage;
          if (count($this->logMessagesStack)) {
            $messageToLog = implode("\n", $this->logMessagesStack) . "\n" . $formattedMessage;
            $this->logMessagesStack = [];
          }
          @file_put_contents($logFilePath, "$messageToLog\n", FILE_APPEND | LOCK_EX);
        }
        else {
          $this->logMessagesStack[] = $formattedMessage;
        }

        if (isset($this->configuration['console-output'])) {
          $this->configuration['console-output']->writeln($formattedMessage);
        }
      }
    }
    catch (Throwable $e) {
      echo $e->getMessage();
    }
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\static_suite\StaticSuiteUserException
   */
  public function setConfiguration(array $configuration) {
    // Check that builder-id option is present.
    if (empty($configuration['builder-id'])) {
      throw new StaticSuiteUserException('Builder ID is empty.');
    }

    if (!array_key_exists($configuration['builder-id'], $this->staticBuilderPluginManager->getLocalDefinitions())) {
      throw new StaticSuiteUserException('Builder "' . $configuration['builder-id'] . '" is not a builder of type "' . StaticBuilderPluginInterface::HOST_MODE_LOCAL . '"');
    }

    $this->staticBuilder = $this->staticBuilderPluginManager->getInstance([
      'plugin_id' => $configuration['builder-id'],
      'configuration' => ['run-mode' => StaticBuilderPluginInterface::RUN_MODE_LIVE],
    ]);

    // Merge config and get a new one.
    $configuration = NestedArray::mergeDeep(
      $this->defaultConfiguration(),
      $configuration
    );

    // Set other config values that shouldn't be overridden.
    $builderConfiguration = $this->staticBuilder->getConfiguration();
    $configuration['base-dir'] = $builderConfiguration['base-dir'];
    $configuration['run-mode-dir'] = $configuration['base-dir'] . '/' . StaticBuilderPluginInterface::RUN_MODE_LIVE;

    // Finally, set configuration.
    $this->configuration = $configuration;
  }

  /**
   * {@inheritdoc}
   *
   * Plugins can define their own configuration by overriding this method.
   */
  public function defaultConfiguration(): array {
    $config = $this->configFactory->get('static_deploy.settings');

    $defaultConfiguration = [
      'env' => $config->get('env'),
      'drush-options' => $config->get('drush_options'),
      'sync' => !empty($config->get('sync')),
      'force' => FALSE,
    ];

    // Merge parent configuration.
    return NestedArray::mergeDeep(
      parent::defaultConfiguration(),
      $defaultConfiguration
    );
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
   * Dispatch StaticDeployEvent.
   *
   * @param string $eventName
   *   The name of the event.
   * @param array $data
   *   Data for the event.
   *
   * @return \Drupal\static_deploy\Event\StaticDeployEvent
   *   The event.
   */
  protected function dispatchEvent(string $eventName, array $data = []): StaticDeployEvent {
    $event = new StaticDeployEvent($this);
    $event->setData($data);

    // Dispatch the event.
    $this->logMessage("EVENT DISPATCH '$eventName' TRIGGERED");
    $this->eventDispatcher->dispatch($event, $eventName);
    $this->logMessage("EVENT DISPATCH '$eventName' DONE");

    // Return the event.
    return $event;
  }

}
