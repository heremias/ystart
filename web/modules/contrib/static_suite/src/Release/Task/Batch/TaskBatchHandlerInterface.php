<?php

namespace Drupal\static_suite\Release\Task\Batch;

/**
 * An interface for classes that provide batch functionality for tasks.
 */
interface TaskBatchHandlerInterface {

  /**
   * Prepare the task data to be used as a batch callback.
   *
   * @param array $taskData
   *   An array with the task data.
   * @param string $runningLabel
   *   The label to be used when the task is running ("Building", "Deploying",
   *   etc.)
   * @param string|null $logUrl
   *   The URL of the log where the task process can be followed.
   *
   * @return array
   *   An array with data ready to be used by a batch callback.
   */
  public function prepareBatchCallbackData(array $taskData, string $runningLabel = 'Running', string $logUrl = NULL): array;

}
