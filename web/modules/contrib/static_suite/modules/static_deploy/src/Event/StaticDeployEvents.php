<?php

namespace Drupal\static_deploy\Event;

/**
 * Events dispatched by Static Deploy.
 *
 * @see \Drupal\static_export\Event\StaticExportEvents for more information
 *   about CHAINED_STEP_START and CHAINED_STEP_END events.
 *
 * ASYNC_PROCESS_FORK_START and ASYNC_PROCESS_FORK_END are executed
 *   independently, before the chained step starts, because they serve as a way
 *   of forking a process.
 */
final class StaticDeployEvents {

  public const ASYNC_PROCESS_FORK_START = 'static_deploy.async_process_fork_start';

  public const ASYNC_PROCESS_FORK_END = 'static_deploy.async_process_fork_end';

  public const CHAINED_STEP_START = 'static_deploy.chained_step_start';

  public const START = 'static_deploy.start';

  public const DEPLOY_START = 'static_deploy.deploy_start';

  public const DEPLOY_END = 'static_deploy.deploy_end';

  public const ROLLBACK_START = 'static_deploy.rollback_start';

  public const ROLLBACK_END = 'static_deploy.rollback_end';

  public const END = 'static_deploy.end';

  public const CHAINED_STEP_END = 'static_deploy.chained_step_end';

}
