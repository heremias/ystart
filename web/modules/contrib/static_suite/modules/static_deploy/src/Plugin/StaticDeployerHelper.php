<?php

namespace Drupal\static_deploy\Plugin;

use Drupal\static_suite\Release\Task\TaskInterface;
use Throwable;

/**
 * Helper methods for Static Builder.
 */
class StaticDeployerHelper implements StaticDeployerHelperInterface {

  /**
   * The static deployer manager.
   *
   * @var StaticDeployerPluginManagerInterface
   */
  protected $staticDeployerPluginManager;

  /**
   * StaticDeployerHelper constructor.
   *
   * @param StaticDeployerPluginManagerInterface $staticDeployerPluginManager
   *   The static deployer manager.
   */
  public function __construct(StaticDeployerPluginManagerInterface $staticDeployerPluginManager) {
    $this->staticDeployerPluginManager = $staticDeployerPluginManager;
  }

  /**
   * {@inheritdoc}
   */
  public function getRunningDeployData(string $deployerId, string $builderId, string $uniqueId = NULL): array {
    $runningDeployData = [];
    try {
      $deployer = $this->staticDeployerPluginManager->getInstance([
        'plugin_id' => $deployerId,
        'configuration' => ['builder-id' => $builderId],
      ]);
      $releaseManager = $deployer->getReleaseManager();
      $taskId = $deployer->getTaskId();
      $taskSupervisor = $releaseManager->getTaskSupervisor();
      if ($uniqueId) {
        $runningDeployData = $taskSupervisor->getReleaseTaskData($uniqueId, $taskId);
      }
      else {
        $runningDeployData = $taskSupervisor->getRunningReleaseTaskData($taskId);
        $lastDoneRelease = $taskSupervisor->getLastReleaseByTaskStatus($taskId, TaskInterface::DONE);
        $lastFailedRelease = $taskSupervisor->getLastReleaseByTaskStatus($taskId, TaskInterface::FAILED);
        $lastRelease = $lastDoneRelease;
        if (
          (!$lastDoneRelease && $lastFailedRelease) ||
          ($lastDoneRelease && $lastFailedRelease && $lastDoneRelease->uniqueId() < $lastFailedRelease->uniqueId())
        ) {
          $lastRelease = $lastFailedRelease;
        }
        $runningDeployData['last'] = $taskSupervisor->getReleaseTaskData($lastRelease->uniqueId(), $taskId);
      }
    }
    catch (Throwable $e) {
      // Do nothing.
    }
    return $runningDeployData;
  }

}
