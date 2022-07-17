<?php

namespace Drupal\static_suite\Release\Task;

use Drupal\Core\File\FileSystemInterface;
use Drupal\static_suite\StaticSuiteException;

/**
 * A class representing a release.
 */
class Task implements TaskInterface {

  /**
   * Path to the directory where task info is stored.
   *
   * @var string
   */
  protected $dir;

  /**
   * Task id.
   *
   * @var string
   */
  protected $id;

  /**
   * File system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * Task constructor.
   *
   * @param string $dir
   *   Path to the directory where task info is stored.
   * @param string $id
   *   An id identifying this task.
   */
  public function __construct(FileSystemInterface $fileSystem, string $dir, string $id) {
    $this->fileSystem = $fileSystem;
    $this->dir = $dir . '/' . filter_var($id, FILTER_SANITIZE_ENCODED);
    $this->id = $id;
  }

  /**
   * Get the filepath for a flag.
   *
   * @param string $name
   *   Flag name.
   *
   * @return string
   *   Flag file path
   */
  protected function getFlagFilePath(string $name): string {
    return $this->dir . '/' . filter_var($name, FILTER_SANITIZE_ENCODED) . '.flag';
  }

  /**
   * {@inheritdoc}
   */
  public function setFlag(string $name, bool $value = TRUE): void {
    $flagFile = $this->getFlagFilePath($name);
    clearstatcache(TRUE, $flagFile);
    if ($value) {
      // Ensure directory for task is ready.
      $taskDir = $this->fileSystem->dirname($flagFile);
      if (!is_dir($taskDir)) {
        $this->fileSystem->mkdir($taskDir, 0777, TRUE);
      }
      if (!file_put_contents($flagFile, 'true')) {
        throw new StaticSuiteException('Unable to create ' . $flagFile);
      }
    }
    elseif (file_exists($flagFile) && !$this->fileSystem->unlink($flagFile)) {
      throw new StaticSuiteException('Unable to delete ' . $flagFile);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function isFlagSet(string $name): bool {
    $flagFile = $this->getFlagFilePath($name);
    clearstatcache(TRUE, $flagFile);
    return is_file($flagFile);
  }

  /**
   * Get flag name for FLAG_STARTED.
   *
   * @return string
   *   Flag name
   */
  protected function getStartedFlagName(): string {
    return $this->id . '-' . self::STARTED;
  }

  /**
   * Get flag name for FLAG_DONE.
   *
   * @return string
   *   Flag name
   */
  protected function getDoneFlagName(): string {
    return $this->id . '-' . self::DONE;
  }

  /**
   * Get flag name for FLAG_FAILED.
   *
   * @return string
   *   Flag name
   */
  protected function getFailedFlagName(): string {
    return $this->id . '-' . self::FAILED;
  }

  /**
   * {@inheritdoc}
   */
  public function isStarted(): bool {
    return $this->isFlagSet($this->getStartedFlagName());
  }

  /**
   * {@inheritdoc}
   */
  public function setStarted(): void {
    $this->setFlag($this->getStartedFlagName(), TRUE);
    $this->setFlag($this->getFailedFlagName(), FALSE);
    $this->setFlag($this->getDoneFlagName(), FALSE);
  }

  /**
   * {@inheritdoc}
   */
  public function isDone(): bool {
    return $this->isFlagSet($this->getDoneFlagName());
  }

  /**
   * {@inheritdoc}
   */
  public function setDone(): void {
    $this->setFlag($this->getFailedFlagName(), FALSE);
    $this->setFlag($this->getDoneFlagName(), TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function isFailed(): bool {
    if ($this->isFlagSet($this->getFailedFlagName())) {
      return TRUE;
    }

    if (!$this->isDone()) {
      $logFilePath = $this->getLogFilePath();
      clearstatcache(TRUE, $logFilePath);
      if (!is_file($logFilePath) || abs(time() - filemtime($logFilePath)) > self::MAX_SECONDS_TO_MARK_AS_FAILED) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function setFailed(): void {
    $this->setFlag($this->getDoneFlagName(), FALSE);
    $this->setFlag($this->getFailedFlagName(), TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function isRunning(): bool {
    return $this->isStarted() && !$this->isDone() && !$this->isFailed();
  }

  /**
   * {@inheritdoc}
   */
  public function getLogFilePath(): string {
    return $this->dir . '/' . $this->id . '.log';
  }

  /**
   * {@inheritdoc}
   */
  public function getProcessBenchmark(): int {
    $benchmark = 0;
    $startTime = $this->getProcessStartTime();
    if ($this->isDone()) {
      $endTime = $this->getProcessEndTime();
    }
    elseif ($this->isFailed()) {
      $endTime = $startTime;
    }
    else {
      $endTime = time();
    }

    if ($startTime !== 0 && $endTime !== 0) {
      $benchmark = $endTime - $startTime;
    }
    return $benchmark;
  }

  /**
   * {@inheritdoc}
   */
  public function getProcessStartTime(): int {
    $startTime = time();
    $startFile = $this->getFlagFilePath($this->getStartedFlagName());
    clearstatcache(TRUE, $startFile);
    if (is_readable($startFile)) {
      $startTime = filemtime($startFile);
    }
    return $startTime;
  }

  /**
   * {@inheritdoc}
   */
  public function getProcessElapsedSeconds(): int {
    if ($this->isDone() || $this->isFailed()) {
      return 0;
    }
    return time() - $this->getProcessStartTime();
  }

  /**
   * {@inheritdoc}
   */
  public function getProcessEndTime(): int {
    $endTime = 0;
    $logFile = $this->getLogFilePath();
    clearstatcache(TRUE, $logFile);
    if (is_readable($logFile)) {
      $endTime = filemtime($logFile);
    }
    return $endTime;
  }

}
