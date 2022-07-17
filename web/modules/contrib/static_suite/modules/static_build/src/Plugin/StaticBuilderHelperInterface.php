<?php

namespace Drupal\static_build\Plugin;

/**
 * An interface with helper methods for Static Builder.
 */
interface StaticBuilderHelperInterface {

  /**
   * Get data from a running build process.
   *
   * @param string $builderId
   *   Id of the StaticBuilder plugin to get data from.
   * @param string $runMode
   *   One of StaticBuilderInterface::RUN_MODE_*.
   * @param string|null $uniqueId
   *   Release's unique id. Optional.
   *
   * @return array
   *   An array with data from the build, or an empty array if not available.
   * @see TaskSupervisorInterface::DEFAULT_TASK_DATA
   */
  public function getRunningBuildData(string $builderId, string $runMode, string $uniqueId = NULL): array;

}
