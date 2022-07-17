<?php

namespace Drupal\static_build\Plugin;

use Drupal\static_suite\Cli\CliCommandFactoryInterface;
use Drupal\static_suite\Plugin\ConfigurableDrushAsyncTaskPluginBase;
use Drupal\static_suite\Release\ReleaseManagerInterface;
use Drupal\static_suite\Utility\SignalHandler;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for plugins that execute a Drush async task based on releases.
 */
abstract class ReleaseBasedConfigurableDrushAsyncTaskPluginBase extends ConfigurableDrushAsyncTaskPluginBase implements ReleaseBasedAsyncTaskPluginInterface {

  /**
   * Release Manager.
   *
   * @var \Drupal\static_suite\Release\ReleaseManagerInterface
   */
  protected $releaseManager;

  /**
   * Constructs a ReleaseBasedTaskPluginBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\static_suite\Cli\CliCommandFactoryInterface $cliCommandFactory
   *   The CLI command factory.
   * @param \Drupal\static_suite\Release\ReleaseManagerInterface $releaseManager
   *   The release manager.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    CliCommandFactoryInterface $cliCommandFactory,
    ReleaseManagerInterface $releaseManager
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $cliCommandFactory);
    $this->releaseManager = $releaseManager;

    // Register shutdown callbacks to mark task as failed if stopped on CLI.
    // PCNTL extension is usually loaded in CLI environments.
    if (extension_loaded('pcntl')) {
      $markAsFailedClosure = function () {
        $ongoingRelease = $this->releaseManager->getLatestRelease();
        $taskId = $this->getTaskId();
        if (
          $ongoingRelease &&
          $this->releaseManager->exists($ongoingRelease->uniqueId()) &&
          !$ongoingRelease->task($taskId)->isDone()
        ) {
          $ongoingRelease->task($taskId)->setFailed();
        }
      };

      // Define handled signals.
      // SIGHUP: hangup detected on controlling terminal or death of controlling
      // process.
      // SIGINT: interrupt from keyboard (Ctrl+C)
      // SIGQUIT: quit from keyboard. (Ctrl+\ or kill -QUIT PID)
      // SIGTERM: termination signal. (kill -15 PID)
      // @see https://man7.org/linux/man-pages/man7/signal.7.html
      $signals = array_filter([
        defined('SIGHUP') ? SIGHUP : NULL,
        defined('SIGINT') ? SIGINT : NULL,
        defined('SIGQUIT') ? SIGQUIT : NULL,
        defined('SIGTERM') ? SIGTERM : NULL,
      ]);
      foreach ($signals as $signo) {
        SignalHandler::register($signo, $markAsFailedClosure);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get("static_suite.cli_command_factory"),
      $container->get("static_suite.release_manager"),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getReleaseManager(bool $initialize = TRUE): ReleaseManagerInterface {
    if ($initialize && (!$this->releaseManager->isInitialized() || $this->releaseManager->getBaseDir() !== $this->configuration['run-mode-dir'])) {
      $this->releaseManager->init($this->configuration['run-mode-dir']);
    }
    return $this->releaseManager;
  }

}
