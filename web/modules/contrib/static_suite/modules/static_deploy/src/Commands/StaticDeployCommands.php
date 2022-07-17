<?php

namespace Drupal\static_deploy\Commands;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\static_deploy\Plugin\StaticDeployerPluginManagerInterface;
use Drupal\static_suite\StaticSuiteUserException;
use Drush\Commands\DrushCommands;
use Throwable;

/**
 * A Drush command file to execute Static Deploys.
 */
class StaticDeployCommands extends DrushCommands {

  /**
   * Drupal config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Static deployer manager.
   *
   * @var \Drupal\static_deploy\Plugin\StaticDeployerPluginManagerInterface
   */
  protected $staticDeployerPluginManager;

  /**
   * StaticDeployCommands constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Drupal config factory.
   * @param \Drupal\static_deploy\Plugin\StaticDeployerPluginManagerInterface $static_deployer_manager
   *   Static deployer manager.
   */
  public function __construct(ConfigFactoryInterface $config_factory, StaticDeployerPluginManagerInterface $static_deployer_manager) {
    parent::__construct();
    $this->configFactory = $config_factory;
    $this->staticDeployerPluginManager = $static_deployer_manager;
  }

  /**
   * Request a Static Deployment.
   *
   * @param string $staticBuilderId
   *   Static builder id. Specifying a builder id is necessary since
   *   each builder defines its own base_dir and we need that information to
   *   get the source that will be deployed.
   * @param string $staticDeployerId
   *   Static deployer id. This option is useful when you need to deploy
   *   the same release to different places (AWS S3, Azure, etc)
   * @param array $execOptions
   *   An associative array of options whose values come
   *   from cli, aliases, config, etc.
   *
   * @command static-deploy:deploy
   * @option force Whether it should deploy an already deployed release.
   * @aliases sdeploy
   *
   * @static_deploy Annotation for drush hooks.
   */
  public function requestDeploy(
    string $staticBuilderId,
    string $staticDeployerId,
    array $execOptions = [
      'force' => FALSE,
    ]): void {
    try {
      $plugin = $this->staticDeployerPluginManager->getInstance([
        'plugin_id' => $staticDeployerId,
        'configuration' => [
          'builder-id' => $staticBuilderId,
          'force' => $execOptions['force'],
          'console-output' => $this->output(),
        ],
      ]
      );
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
