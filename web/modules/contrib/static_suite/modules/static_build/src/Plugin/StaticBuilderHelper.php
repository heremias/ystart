<?php

namespace Drupal\static_build\Plugin;

use Drupal\static_suite\Release\Task\TaskInterface;
use Throwable;

/**
 * Helper methods for Static Builder.
 */
class StaticBuilderHelper implements StaticBuilderHelperInterface {

  /**
   * The static builder manager.
   *
   * @var \Drupal\static_build\Plugin\StaticBuilderPluginManagerInterface
   */
  protected $staticBuilderPluginManager;

  /**
   * StaticBuilderHelper constructor.
   *
   * @param \Drupal\static_build\Plugin\StaticBuilderPluginManagerInterface $staticBuilderPluginManager
   *   The static builder manager.
   */
  public function __construct(StaticBuilderPluginManagerInterface $staticBuilderPluginManager) {
    $this->staticBuilderPluginManager = $staticBuilderPluginManager;
  }

  /**
   * {@inheritdoc}
   */
  public function getRunningBuildData(string $builderId, string $runMode, string $uniqueId = NULL): array {
    $runningBuildData = [];
    try {
      $builder = $this->staticBuilderPluginManager->getInstance([
        'plugin_id' => $builderId,
        'configuration' => ['run-mode' => $runMode],
      ]);
      $releaseManager = $builder->getReleaseManager();
      $taskId = $builder->getTaskId();
      $taskSupervisor = $releaseManager->getTaskSupervisor();
      if ($uniqueId) {
        $runningBuildData = $taskSupervisor->getReleaseTaskData($uniqueId, $taskId);
      }
      else {
        $runningBuildData = $taskSupervisor->getRunningReleaseTaskData($taskId);
        $lastDoneRelease = $taskSupervisor->getLastReleaseByTaskStatus($taskId, TaskInterface::DONE);
        $lastFailedRelease = $taskSupervisor->getLastReleaseByTaskStatus($taskId, TaskInterface::FAILED);
        $lastRelease = $lastDoneRelease;
        if (
          (!$lastDoneRelease && $lastFailedRelease) ||
          ($lastDoneRelease && $lastFailedRelease && $lastDoneRelease->uniqueId() < $lastFailedRelease->uniqueId())
        ) {
          $lastRelease = $lastFailedRelease;
        }
        $runningBuildData['last'] = $taskSupervisor->getReleaseTaskData($lastRelease->uniqueId(), $taskId);
      }
    }
    catch (Throwable $e) {
      // Do nothing.
    }

    return $runningBuildData;
  }

}
