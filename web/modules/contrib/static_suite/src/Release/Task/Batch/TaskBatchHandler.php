<?php

namespace Drupal\static_suite\Release\Task\Batch;

use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * A class that provides batch functionality for tasks.
 */
class TaskBatchHandler implements TaskBatchHandlerInterface {

  use StringTranslationTrait;

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
  public function prepareBatchCallbackData(array $taskData, string $runningLabel = 'Running', string $logUrl = NULL): array {
    // Set 100 if the task is already finished.
    $percentage = (!$taskData['unique-id'] && $taskData['percentage'] === 0) ? 100 : $taskData['percentage'];

    if (!$taskData['is-started'] || $taskData['is-failed']) {
      if ($logUrl) {
        $errorMessage = $this->t('<a href="@logUrl" title="View log" target="_blank">An error has occurred</a>', [
          '@logUrl' => $logUrl,
        ]);
      }
      else {
        $errorMessage = $this->t('An error has occurred');
      }
      return [
        'status' => FALSE,
        'message' => 'FAILED: ' . $errorMessage,
      ];
    }

    if ($percentage === 100) {
      if ($logUrl) {
        $label = $this->t('<a href="@logUrl" title="View log" target="_blank">Finished (@secondss)</a>', [
          '@logUrl' => $logUrl,
          '@seconds' => $taskData['benchmark'],
        ]);
      }
      else {
        $label = $this->t('Finished (@secondss)', ['@seconds' => $taskData['benchmark']]);
      }
      $message = $label;
    }
    else {
      if ($logUrl) {
        $label = $this->t('<a href="@logUrl" title="View log" target="_blank">@runningLabel...</a>', [
          '@logUrl' => $logUrl,
          '@runningLabel' => $runningLabel,
        ]);
      }
      else {
        $label = $this->t('@runningLabel...', ['@runningLabel' => $runningLabel]);
      }
      $message = $this->t('@secondss', ['@seconds' => $taskData['elapsed-seconds']]);
    }

    return [
      'status' => TRUE,
      'percentage' => (string) $percentage,
      'message' => $message,
      'label' => $label,
    ];
  }

}
