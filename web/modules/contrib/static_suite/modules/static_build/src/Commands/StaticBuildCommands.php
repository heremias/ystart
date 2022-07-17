<?php

namespace Drupal\static_build\Commands;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\static_build\Plugin\StaticBuilderPluginInterface;
use Drupal\static_build\Plugin\StaticBuilderPluginManagerInterface;
use Drupal\static_suite\StaticSuiteUserException;
use Drush\Commands\DrushCommands;
use Throwable;

/**
 * A Drush command file to work with Static Build.
 */
class StaticBuildCommands extends DrushCommands {

  /**
   * Config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Static builder.
   *
   * @var \Drupal\static_build\Plugin\StaticBuilderPluginManagerInterface
   */
  protected $staticBuilderPluginManager;

  /**
   * StaticBuildCommands constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Config factory.
   * @param \Drupal\static_build\Plugin\StaticBuilderPluginManagerInterface $staticBuilderPluginManager
   *   Static Builder plugin manager.
   */
  public function __construct(ConfigFactoryInterface $config_factory, StaticBuilderPluginManagerInterface $staticBuilderPluginManager) {
    parent::__construct();
    $this->configFactory = $config_factory;
    $this->staticBuilderPluginManager = $staticBuilderPluginManager;
  }

  /**
   * Requests a Static Build.
   *
   * @param string $builderId
   *   Id of the builder that will run the build.
   * @param string|null $runMode
   *   The run mode to execute the StaticBuilder. Usually "preview" or "live".
   * @param array $execOptions
   *   An associative array of options.
   *   lock-mode: which data lock must be honored when deciding whether build
   *   must start or not. Usually "preview" or "live", and usually the same as
   *   $runMode.
   *   request-deploy: flag to tell whether a deploy must be requested.
   *
   * @command static-build:build
   *
   * @aliases sbuild
   *
   * @static_build Annotation for drush hooks.
   */
  public function requestBuild(
    string $builderId,
    string $runMode = NULL,
    array $execOptions = [
      'lock-mode' => NULL,
      'request-deploy' => FALSE,
    ]
  ): void {
    try {
      $plugin = $this->staticBuilderPluginManager->getInstance([
        'plugin_id' => $builderId,
        'configuration' => [
          'run-mode' => $runMode ?: StaticBuilderPluginInterface::RUN_MODE_LIVE,
          'lock-mode' => $execOptions['lock-mode'],
          'request-deploy' => $execOptions['request-deploy'],
          'console-output' => $this->output(),
        ],
      ]);
      $plugin->init();
    }
    catch (StaticSuiteUserException | PluginException $e) {
      $this->logger()->error($e->getMessage());
    }
    catch (Throwable $e) {
      $this->logger()->error($e);
    }
  }

}
