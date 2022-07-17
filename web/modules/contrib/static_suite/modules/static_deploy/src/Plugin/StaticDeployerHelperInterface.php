<?php

namespace Drupal\static_deploy\Plugin;

/**
 * An interface with helper methods for Static Deployer.
 */
interface StaticDeployerHelperInterface {

  /**
   * Get data from a running deploy process.
   *
   * @param string $deployerId
   *   Id of the @StaticDeployer plugin that is executing a deployment.
   * @param string $builderId
   *   Id of the @StaticBuilder plugin that is being deployed.
   * @param string|null $uniqueId
   *   Release's unique id. Optional.
   *
   * @return array
   *   An array with data from the deployment, or an empty array if not
   *   available.
   * @see TaskSupervisorInterface::DEFAULT_TASK_DATA
   */
  public function getRunningDeployData(string $deployerId, string $builderId, string $uniqueId = NULL): array;

}
