<?php

namespace Drupal\static_suite\Release\Task;

use Drupal\static_suite\Release\ReleaseInterface;
use Drupal\static_suite\Release\ReleaseManagerInterface;

/**
 * An interface for task supervisors.
 */
interface TaskSupervisorInterface {

  public const DEFAULT_TASK_DATA = [
    'unique-id' => NULL,
    'elapsed-seconds' => 0,
    'remaining-seconds' => 0,
    'percentage' => 0,
    'benchmark' => 0,
    'is-started' => FALSE,
    'is-done' => FALSE,
    'is-failed' => FALSE,
    'is-running' => FALSE,
  ];

  /**
   * Set the release manager.
   *
   * @param \Drupal\static_suite\Release\ReleaseManagerInterface $releaseManager
   *   The release manager.
   */
  public function setReleaseManager(ReleaseManagerInterface $releaseManager): void;

  /**
   * Get last release by task status (started, done, failed, or running).
   *
   * @param string $taskId
   *   Task id to check.
   * @param string $status
   *   Task status to search for (started, done, failed, or running).
   *
   * @return \Drupal\static_suite\Release\ReleaseInterface|null
   *   Last release or null if nothing found
   */
  public function getLastReleaseByTaskStatus(string $taskId, string $status): ?ReleaseInterface;

  /**
   * Get average task time (the arithmetic mean).
   *
   * Is the sum of all the task times divided by the number of values.
   *
   * @param string $taskId
   *   Task id to check.
   *
   * @return int
   *   Average task time in seconds
   */
  public function getAverageTaskTime(string $taskId): int;

  /**
   * Get percentage of a release task.
   *
   * @param string $uniqueId
   *   A unique Id identifying a release.
   * @param string $taskId
   *   Task id to check.
   *
   * @return int
   *   Task percentage.
   */
  public function getTaskPercentage(string $uniqueId, string $taskId): int;

  /**
   * Get estimated number of seconds remaining for a release task to finish.
   *
   * @param string $uniqueId
   *   A unique Id identifying a release.
   * @param string $taskId
   *   Task id to check.
   *
   * @return int
   *   Remaining seconds.
   */
  public function getRemainingSeconds(string $uniqueId, string $taskId): int;

  /**
   * Get data from a task inside a running release.
   *
   * @param string $taskId
   *   Task id to check.
   *
   * @return array
   *   An array with data from the release.
   */
  public function getRunningReleaseTaskData(string $taskId): array;

  /**
   * Get task data from a release.
   *
   * @param string $uniqueId
   *   Release's unique id.
   * @param string $taskId
   *   Task id to check.
   *
   * @return array
   *   An array with data from the release.
   */
  public function getReleaseTaskData(string $uniqueId, string $taskId): array;

}
