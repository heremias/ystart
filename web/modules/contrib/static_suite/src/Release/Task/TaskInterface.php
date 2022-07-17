<?php

namespace Drupal\static_suite\Release\Task;

/**
 * An interface for tasks.
 */
interface TaskInterface {

  public const STARTED = 'started';

  public const DONE = 'done';

  public const FAILED = 'failed';

  public const RUNNING = 'running';

  public const MAX_SECONDS_TO_MARK_AS_FAILED = 300;

  /**
   * Sets a flag on disk, inside a release's dir.
   *
   * @param string $name
   *   Flag name.
   * @param bool $value
   *   TRUE to set flag, and FALSE to unset it.
   *
   * @throws \Drupal\static_suite\StaticSuiteException
   */
  public function setFlag(string $name, bool $value): void;

  /**
   * Tells whether a flag is set.
   *
   * @param string $name
   *   Flag name.
   *
   * @return bool
   *   TRUE if it's set, FALSE otherwise.
   */
  public function isFlagSet(string $name): bool;

  /**
   * Check if this release has started a task.
   *
   * @return bool
   *   True if it has started a task.
   */
  public function isStarted(): bool;

  /**
   * Set this release has started a task.
   *
   * @throws \Drupal\static_suite\StaticSuiteException
   */
  public function setStarted(): void;

  /**
   * Check if this release has finished a task.
   *
   * @return bool
   *   True if it has finished a task.
   */
  public function isDone(): bool;

  /**
   * Set this release has finished a task.
   *
   * @throws \Drupal\static_suite\StaticSuiteException
   */
  public function setDone(): void;

  /**
   * Set this release has failed finishing a task.
   *
   * @throws \Drupal\static_suite\StaticSuiteException
   */
  public function setFailed(): void;

  /**
   * Check if this release has failed finishing a task.
   *
   * @return bool
   *   True if task hasn't finished.
   */
  public function isFailed(): bool;

  /**
   * Check if this release is running a task.
   *
   * @return bool
   *   True if task is running.
   */
  public function isRunning(): bool;

  /**
   * Get this release's task log file path.
   *
   * @return string
   *   This release's task log file.
   */
  public function getLogFilePath(): string;

  /**
   * Get time taken to process this release (task time + other operations)
   *
   * @return int
   *   Elapsed time in seconds.
   */
  public function getProcessBenchmark(): int;

  /**
   * Get process start time for this release.
   *
   * @return int
   *   Seconds since unix epoch.
   */
  public function getProcessStartTime(): int;

  /**
   * Get the number of seconds passed since the process started.
   *
   * @return int
   *   Seconds elapsed.
   */
  public function getProcessElapsedSeconds(): int;

  /**
   * Get process end time for this release (task time + other operations)
   *
   * @return int
   *   Seconds since unix epoch.
   */
  public function getProcessEndTime(): int;

}
