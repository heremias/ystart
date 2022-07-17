<?php

namespace Drupal\static_build\Event;

/**
 * Events dispatched by Static Build.
 *
 * @see \Drupal\static_export\Event\StaticExportEvents for more information
 *   about CHAINED_STEP_START and CHAINED_STEP_END events.
 */
final class StaticBuildEvents {

  public const CHAINED_STEP_START = 'static_build.chained_step_start';

  public const ASYNC_PROCESS_FORK_START = 'static_build.async_process_fork_start';

  public const ASYNC_PROCESS_FORK_END = 'static_build.async_process_fork_end';

  public const START = 'static_build.start';

  public const BUILD_RUN_START = 'static_build.build_run_start';

  public const BUILD_LOOP_START = 'static_build.build_loop_start';

  public const RELEASE_DIR_CREATION_START = 'static_build.release_dir_creation_start';

  public const RELEASE_DIR_CREATION_END = 'static_build.release_dir_creation_end';

  public const DATA_COPYING_START = 'static_build.data_copying_start';

  public const DATA_COPYING_END = 'static_build.data_copying_end';

  public const PRE_BUILD_START = 'static_build.pre_build_start';

  public const PRE_BUILD_END = 'static_build.pre_build_end';

  public const BUILD_START = 'static_build.build_start';

  public const BUILD_END = 'static_build.build_end';

  public const POST_BUILD_START = 'static_build.post_build_start';

  public const POST_BUILD_END = 'static_build.post_build_end';

  public const PUBLISH_RELEASE_START = 'static_build.publish_release_start';

  public const PUBLISH_RELEASE_END = 'static_build.publish_release_end';

  public const OLD_RELEASES_DELETION_START = 'static_build.old_releases_deletion_start';

  public const OLD_RELEASES_DELETION_END = 'static_build.old_releases_deletion_end';

  public const BUILD_LOOP_END = 'static_build.build_loop_end';

  public const BUILD_RUN_END = 'static_build.build_run_end';

  public const END = 'static_build.end';

  public const CHAINED_STEP_END = 'static_build.chained_step_end';

}
