<?php

namespace Drupal\static_deploy\Plugin;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Component\Plugin\DependentPluginInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\static_build\Plugin\ReleaseBasedAsyncTaskPluginInterface;

/**
 * Defines an interface for Static Deployer plugins.
 */
interface StaticDeployerPluginInterface extends ReleaseBasedAsyncTaskPluginInterface, ConfigurableInterface, DependentPluginInterface, PluginInspectionInterface {

  public const DRUSH_ASYNC_COMMAND = 'drush static-deploy:deploy';

  public const TASK_ID = 'deploy';

  /**
   * Execute deploy operations.
   *
   * This method should be over ridden by plugins to reflect each use case.
   *
   * @throws \Drupal\static_suite\StaticSuiteException
   */
  public function deploy(): void;

  /**
   * Execute rollback operations on any failure.
   *
   * This method should be over ridden by plugins to reflect each use case.
   *
   * @throws \Drupal\static_suite\StaticSuiteException
   */
  public function rollback(): void;

}
