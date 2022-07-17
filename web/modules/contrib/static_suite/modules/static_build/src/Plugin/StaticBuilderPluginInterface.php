<?php

namespace Drupal\static_build\Plugin;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Component\Plugin\DependentPluginInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;

/**
 * Defines an interface for Static Builder plugins.
 */
interface StaticBuilderPluginInterface extends ReleaseBasedAsyncTaskPluginInterface, ConfigurableInterface, DependentPluginInterface, PluginInspectionInterface {

  public const HOST_MODE_LOCAL = 'local';

  public const HOST_MODE_CLOUD = 'cloud';

  public const RUN_MODE_PREVIEW = 'preview';

  public const RUN_MODE_LIVE = 'live';

  public const LOCK_MODE_PREVIEW = 'preview';

  public const LOCK_MODE_LIVE = 'live';

  public const DRUSH_ASYNC_COMMAND = 'drush static-build:build';

  public const TASK_ID = 'build';

  public const BUILD_DIR = '.build';

  public const LOG_DIR = 'log';

  public const LAST_BUILD_LOG_NAME = 'last-build.log';

  /**
   * Execute prebuild operations.
   *
   * This method should be over ridden by plugins to reflect each use case.
   */
  public function preBuild(): void;

  /**
   * Executes a build, typically a bash command running a SSG.
   *
   * This method should be over ridden by plugins to reflect each use case.
   *
   * @throws \Drupal\static_suite\StaticSuiteException
   */
  public function build(): void;

  /**
   * Execute post build operations.
   *
   * This method should be over ridden by plugins to reflect each use case.
   */
  public function postBuild(): void;

  /**
   * Tells whether a builder's host is local or not.
   *
   * @return bool
   *   TRUE if it's local or FALSE otherwise
   */
  public function isLocal(): bool;

  /**
   * Tells whether a builder's host is cloud or not.
   *
   * @return bool
   *   TRUE if it's cloud or FALSE otherwise
   */
  public function isCloud(): bool;

  /**
   * Logs a message.
   *
   * Don't throw any error. It's essential to ensure proper recovery on
   * try-catch blocks.
   *
   * @param string $message
   *   Message to log.
   * @param bool $mustFormat
   *   Optional. Boolean to indicate that message must be formatted.
   */
  public function logMessage(string $message, bool $mustFormat = TRUE);

}
