<?php

namespace Drupal\static_suite\Release\Task;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\static_suite\Release\ReleaseInterface;
use Drupal\static_suite\Release\ReleaseManagerInterface;
use Drupal\static_suite\Utility\UniqueIdHelperInterface;
use Throwable;

/**
 * A class to control tasks inside releases.
 */
class TaskSupervisor implements TaskSupervisorInterface {

  /**
   * Unique ID helper.
   *
   * @var \Drupal\static_suite\Utility\UniqueIdHelperInterface
   */
  protected $uniqueIdHelper;

  /**
   * Drupal config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The release manager.
   *
   * @var \Drupal\static_suite\Release\ReleaseManagerInterface
   */
  protected $releaseManager;

  /**
   * Task supervisor constructor.
   *
   * Although this supervisor can be directly accessed and used, it should be
   * accessed through a plugin's getReleaseManager()->getTaskSupervisor()
   * method. This way, release manager is already configured and initialized,
   * honoring that plugin's configuration. This is also essential to allow
   * different plugins with different base directories (a Work In Progress not
   * already finished).
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Drupal config factory.
   * @param \Drupal\static_suite\Utility\UniqueIdHelperInterface $unique_id_helper
   *   Static Suite utils.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    UniqueIdHelperInterface $unique_id_helper
  ) {
    $this->configFactory = $config_factory;
    $this->uniqueIdHelper = $unique_id_helper;
  }

  /**
   * Set the release manager.
   *
   * @param \Drupal\static_suite\Release\ReleaseManagerInterface $releaseManager
   *   The release manager.
   */
  public function setReleaseManager(ReleaseManagerInterface $releaseManager): void {
    $this->releaseManager = $releaseManager;
  }

  /**
   * {@inheritdoc}
   */
  public function getLastReleaseByTaskStatus(string $taskId, string $status): ?ReleaseInterface {
    if (in_array($status, [
      TaskInterface::STARTED,
      TaskInterface::DONE,
      TaskInterface::FAILED,
    ], TRUE)) {
      $allReleases = $this->releaseManager->getAllReleases();
      foreach ($allReleases as $release) {
        $task = $release->task($taskId);
        if ($status === TaskInterface::STARTED && $task->isStarted()) {
          return $release;
        }

        if ($status === TaskInterface::DONE && $task->isDone()) {
          return $release;
        }

        if ($status === TaskInterface::FAILED && $task->isFailed()) {
          return $release;
        }
      }
    }
    elseif ($status === TaskInterface::RUNNING) {
      // When status is "running", only the latest release should be taken into
      // account.
      $latestRelease = $this->releaseManager->getLatestRelease();
      if ($latestRelease && $latestRelease->task($taskId)->isRunning()) {
        return $latestRelease;
      }
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getAverageTaskTime(string $taskId = NULL): int {
    // Use the default timeout for build semaphores, which should be the maximum
    // amount of time that a build could take.
    $averageTime = $this->configFactory->get('static_build.settings')
      ->get('semaphore_timeout');

    $numberOfReleasesToKeep = $this->configFactory->get('static_build.settings')
      ->get('number_of_releases_to_keep');
    $releasesToCalculateData = array_slice(
      $this->releaseManager->getAllReleases(),
      0,
      $numberOfReleasesToKeep
    );

    $numberOfValidReleases = 0;
    if ($releasesToCalculateData) {
      $timeSum = 0;
      foreach ($releasesToCalculateData as $release) {
        // Check if relase is done. We could have some releases but all of them
        // failed.
        if ($release->task($taskId)->isDone()) {
          $timeSum += $release->task($taskId)->getProcessBenchmark();
          $numberOfValidReleases++;
        }
      }
      if ($numberOfValidReleases) {
        $averageTime = (int) round($timeSum / $numberOfValidReleases, 0);
      }
    }

    return $averageTime;
  }

  /**
   * {@inheritdoc}
   */
  public function getTaskPercentage(string $uniqueId, string $taskId): int {
    $ongoingRelease = $this->releaseManager->create($uniqueId);
    $ongoingReleaseTask = $ongoingRelease->task($taskId);

    if ($ongoingReleaseTask->isDone()) {
      $percentage = 100;
    }
    elseif ($ongoingReleaseTask->isFailed()) {
      $percentage = 0;
    }
    else {
      $medianTaskTime = $this->getAverageTaskTime($taskId);
      $elapsedSeconds = $ongoingReleaseTask->getProcessElapsedSeconds();
      $percentage = $medianTaskTime ? (int) round(($elapsedSeconds / $medianTaskTime) * 100, 0) : 0;
      if ($percentage < 1) {
        // Use a minimum value of 1%, so it's clear that a process is started.
        // This is also a requirement for JS batch processes, which don't appear
        // on screen until its percentage is greater than 0.
        $percentage = 1;
      }
      elseif ($percentage > 99) {
        // Don't show 100% because we don't really know when it's going to
        // finish.
        $percentage = 99;
      }
    }

    return $percentage;
  }

  /**
   * {@inheritdoc}
   */
  public function getRemainingSeconds(string $uniqueId, string $taskId): int {
    $remainingSeconds = 0;
    $release = $this->releaseManager->create($uniqueId);
    $releaseTask = $release->task($taskId);

    if ($releaseTask->isRunning()) {
      // This could be a negative number. Let's keep it as-is, to denote a
      // task is taking more time than expected.
      $remainingSeconds = $this->getAverageTaskTime($taskId) - $releaseTask->getProcessElapsedSeconds();
    }

    return $remainingSeconds;
  }

  /**
   * {@inheritdoc}
   */
  public function getRunningReleaseTaskData(string $taskId): array {
    $data = self::DEFAULT_TASK_DATA;
    $runningRelease = $this->getLastReleaseByTaskStatus($taskId, TaskInterface::RUNNING);
    if ($runningRelease) {
      $data = $this->getReleaseTaskData($runningRelease->uniqueId(), $taskId);
    }
    return $data;
  }

  /**
   * {@inheritdoc}
   */
  public function getReleaseTaskData(string $uniqueId, string $taskId): array {
    $data = self::DEFAULT_TASK_DATA;
    $release = $this->releaseManager->create($uniqueId);
    $releaseTask = $release->task($taskId);
    if ($this->releaseManager->exists($uniqueId)) {
      $data['unique-id'] = $uniqueId;
      $data['elapsed-seconds'] = $releaseTask->getProcessElapsedSeconds();
      $data['remaining-seconds'] = $this->getRemainingSeconds($uniqueId, $taskId);
      $data['percentage'] = $this->getTaskPercentage($uniqueId, $taskId);
      $data['benchmark'] = $releaseTask->getProcessBenchmark();
      $data['is-started'] = $releaseTask->isStarted();
      $data['is-done'] = $releaseTask->isDone();
      $data['is-failed'] = $releaseTask->isFailed();
      $data['is-running'] = $releaseTask->isRunning();
    }

    $uniqueIdTimestamp = 0;
    try {
      $uniqueIdDate = $this->uniqueIdHelper->getDateFromUniqueId($uniqueId);
      $uniqueIdTimestamp = $uniqueIdDate->format("U.u");
    }
    catch (Throwable $e) {
      // Do nothing.
    }
    $data['unique-id-timestamp'] = $uniqueIdTimestamp;

    return $data;
  }

}
