<?php

namespace Drupal\static_build\Plugin;

use Drupal\static_suite\Plugin\AsyncTaskPluginInterface;
use Drupal\static_suite\Release\ReleaseManagerInterface;

/**
 * Interface for plugins that execute an async task based on releases.
 */
interface ReleaseBasedAsyncTaskPluginInterface extends AsyncTaskPluginInterface {

  /**
   * Get the configured release manager.
   *
   * @param bool $initialize
   *   Optional flag to tell whether the release manager should be initialized
   *   before returning it or not. True by default. If initialized, it will try
   *   to create the releases directory, and will throw an exception if it can
   *   create it. Thus, is sometimes useful to get the release manager just
   *   without initializing it, when the releases directory is not required to
   *   be present.
   *
   * @return \Drupal\static_suite\Release\ReleaseManagerInterface
   *   The current release manager.
   *
   * @throws \Drupal\static_suite\StaticSuiteException
   *
   * @todo Move this interface to static_suite core.
   */
  public function getReleaseManager(bool $initialize = TRUE): ReleaseManagerInterface;

}
