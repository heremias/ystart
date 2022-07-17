<?php

namespace Drupal\static_suite\Release;

use Drupal\Core\File\FileSystemInterface;
use Drupal\static_build\Plugin\StaticBuilderPluginInterface;
use Drupal\static_suite\Release\Task\TaskFactoryInterface;
use Drupal\static_suite\Release\Task\TaskInterface;
use Drupal\static_suite\StaticSuiteException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * A class representing a release.
 */
class Release implements ReleaseInterface {

  /**
   * The directory where all releases are stored.
   *
   * @var string
   */
  protected $allReleasesDir;

  /**
   * A unique Id identifying this release.
   *
   * @var string
   */
  protected $uniqueId;

  /**
   * The directory where this release is stored.
   *
   * @var string
   */
  protected $dir;

  /**
   * Task factory.
   *
   * @var \Drupal\static_suite\Release\Task\TaskFactoryInterface
   */
  protected $taskFactory;

  /**
   * Array of added tasks.
   *
   * @var \Drupal\static_suite\Release\Task\TaskInterface[]
   */
  protected $tasks = [];

  /**
   * File system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * Release constructor.
   *
   * @param \Drupal\Core\File\FileSystemInterface $fileSystem
   *   File system service.
   * @param string $allReleasesDir
   *   Dir where releases are stored.
   * @param string $uniqueId
   *   A unique id identifying this release.
   * @param \Drupal\static_suite\Release\Task\TaskFactoryInterface $taskFactory
   *   A task factory.
   */
  public function __construct(FileSystemInterface $fileSystem, string $allReleasesDir, string $uniqueId, TaskFactoryInterface $taskFactory) {
    $this->fileSystem = $fileSystem;
    $this->allReleasesDir = $allReleasesDir;
    $this->uniqueId = $uniqueId;
    $this->taskFactory = $taskFactory;
    $this->dir = $allReleasesDir . '/' . $uniqueId;
  }

  /**
   * {@inheritdoc}
   */
  public function task(string $id): TaskInterface {
    if (!isset($this->tasks[$id])) {
      $this->tasks[$id] = $this->taskFactory->create($this->fileSystem, $this->getTasksDir(), $id);
    }
    return $this->tasks[$id];
  }

  /**
   * Get default task id.
   *
   * @return string
   *   The default task id.
   */
  protected function getDefaultTaskId(): string {
    return StaticBuilderPluginInterface::TASK_ID;
  }

  /**
   * {@inheritdoc}
   */
  public function getDir(): string {
    return $this->dir;
  }

  /**
   * {@inheritdoc}
   */
  public function getMetadataDir(): string {
    return $this->dir . '/' . self::METADATA_DIR;
  }

  /**
   * {@inheritdoc}
   */
  public function getTasksDir(): string {
    return $this->dir . '/' . self::TASKS_DIR;
  }

  /**
   * {@inheritdoc}
   */
  public function uniqueId(): string {
    return $this->uniqueId;
  }

  /**
   * {@inheritdoc}
   */
  public function createReleaseDir(): bool {
    $result = $this->fileSystem->mkdir($this->dir);
    if ($result === FALSE) {
      throw new StaticSuiteException('Unable to create release dir ' . $this->dir);
    }
    $this->createMetadataDir();
    $this->initializeMetadataDir();
    $this->createTasksDir();
    return TRUE;
  }

  /**
   * Create metadata dir inside release dir.
   *
   * @return bool
   *   True on success.
   *
   * @throws \Drupal\static_suite\StaticSuiteException
   */
  protected function createMetadataDir(): bool {
    $metadataDir = $this->getMetadataDir();
    $mkdirResult = $this->fileSystem->mkdir($metadataDir);
    if ($mkdirResult === FALSE) {
      throw new StaticSuiteException('Unable to create metadata dir ' . $metadataDir);
    }

    return TRUE;
  }

  /**
   * Initializes metadata dir.
   *
   * @return bool
   *   True on success.
   *
   * @throws \Drupal\static_suite\StaticSuiteException
   */
  protected function initializeMetadataDir(): bool {
    $uniqueIdFile = $this->dir . '/' . self::UNIQUE_ID_FILE;
    $result = file_put_contents($uniqueIdFile, $this->uniqueId());
    if ($result === FALSE) {
      throw new StaticSuiteException('Unable to initialize metadata dir with file ' . $uniqueIdFile);
    }
    return TRUE;
  }

  /**
   * Create metadata dir inside release dir.
   *
   * @return bool
   *   True on success.
   *
   * @throws \Drupal\static_suite\StaticSuiteException
   */
  protected function createTasksDir(): bool {
    $mkdirResult = $this->fileSystem->mkdir($this->getTasksDir());
    if ($mkdirResult === FALSE) {
      throw new StaticSuiteException('Unable to create tasks dir ' . $this->getTasksDir());
    }

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function deleteReleaseDir(): bool {
    return $this->deleteDirsAndFiles("");
  }

  /**
   * Make a path absolute to a directory.
   *
   * @param string $dir
   *   Directory path that should be suffixed.
   * @param string $source
   *   Relative path to be transformed.
   *
   * @return string
   *   Absolute path.
   */
  protected function makePathAbsolute(string $dir, string $source): string {
    if (!preg_match("/^\//", $source)) {
      $source = $dir . '/' . $source;
    }
    return $source;
  }

  /**
   * {@inheritdoc}
   */
  public function copyToDir(string $source, string $localDestination = '', array $excludedPaths = [], bool $delete = FALSE): bool {
    // Make this path absolute if its relative.
    $source = $this->makePathAbsolute($this->dir, $source);

    clearstatcache(TRUE);
    if (!is_readable($source)) {
      throw new StaticSuiteException("Source $source not readable.");
    }

    if (strpos($localDestination, '/..') !== FALSE || strpos($localDestination, '../') !== FALSE) {
      throw new StaticSuiteException("Wrong parameter localDestination ($localDestination) must be a path inside $this->dir.");
    }

    $destination = $this->dir . "/" . $localDestination;
    $excludeOptions = "";
    $excludedPaths[] = ReleaseInterface::METADATA_DIR;
    foreach ($excludedPaths as $excludedPath) {
      $excludeOptions .= "--exclude='$excludedPath' ";
    }
    clearstatcache(TRUE);
    if (is_file($source) || is_link($source)) {
      $command = "cp -a $source $destination";
    }
    else {
      $command = "rsync -av $excludeOptions $source/ $destination";
      if ($delete) {
        $command .= ' --delete';
      }
    }
    exec($command, $output, $returnValue);
    if ($returnValue !== 0) {
      throw new StaticSuiteException("Unable to copy $source to $localDestination:\n" . implode("\n", $output));
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function moveToDir(string $source, string $localDestination = ''): bool {
    // Make this path absolute if its relative.
    $source = $this->makePathAbsolute($this->dir, $source);

    clearstatcache(TRUE);
    if (!is_readable($source)) {
      throw new StaticSuiteException("Source $source not readable.");
    }

    if (strpos($localDestination, '/..') !== FALSE || strpos($localDestination, '../') !== FALSE) {
      throw new StaticSuiteException("Wrong parameter localDestination ($localDestination) must be a path inside $this->dir.");
    }

    $destination = $this->dir . "/" . $localDestination;
    clearstatcache(TRUE);
    if (is_file($source)) {
      $command = "mv $source $destination";
    }
    else {
      $command = "mv $source/* $destination";
    }
    exec($command, $output, $returnValue);
    if ($returnValue !== 0) {
      throw new StaticSuiteException("Unable to move $source to $localDestination:\n" . implode("\n", $output));
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function deleteFiles(string $localPath, array $excludes = []): bool {
    $realLocalPath = $this->fileSystem->realpath($this->dir . "/" . $localPath);
    if (!$realLocalPath || strpos($realLocalPath, $this->dir) !== 0) {
      throw new StaticSuiteException("Wrong parameter localPath ($localPath).");
    }

    $files = glob($realLocalPath . '/{,.}[!.,!..]*', GLOB_MARK | GLOB_BRACE);
    foreach ($files as $file) {
      clearstatcache(TRUE);
      if (is_file($file) && !in_array($this->fileSystem->basename($file), $excludes, TRUE) && !$this->fileSystem->unlink($file)) {
        throw new StaticSuiteException("Unable to delete file $file.");
      }
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function deleteDirs(string $localPath, array $excludes = []): bool {
    $realLocalPath = $this->fileSystem->realpath($this->dir . "/" . $localPath);
    if (!$realLocalPath || strpos($realLocalPath, $this->dir) !== 0) {
      throw new StaticSuiteException("Wrong parameter localPath ($localPath).");
    }

    $dirs = glob($realLocalPath . '/{,.}[!.,!..]*', GLOB_MARK | GLOB_BRACE);
    foreach ($dirs as $dir) {
      // Remove trailing slash to properly check for directory type.
      // Symlinks are not detected if they have a trailing slash, because they
      // point to the target.
      $dir = rtrim($dir, '/');
      clearstatcache(TRUE);
      if (is_dir($dir) && !in_array($this->fileSystem->basename($dir), $excludes, TRUE)) {
        // Symlinks cannot be recursively deleted -target would be deleted!!-.
        clearstatcache(TRUE);
        $command = is_link($dir) ? "rm -f $dir" : "rm -Rf $dir";
        exec($command, $output, $exitCode);
        if ($exitCode !== 0) {
          throw new StaticSuiteException("Unable to delete dir $dir:\n" . implode("\n", $output));
        }
      }
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function deleteDirsAndFiles(string $localPath): bool {
    clearstatcache(TRUE);
    $realLocalPath = $this->fileSystem->realpath($this->dir . "/" . $localPath);
    if (!$realLocalPath || strpos($realLocalPath, $this->dir) !== 0) {
      throw new StaticSuiteException("Wrong parameter localPath ($localPath).");
    }

    exec("rm -Rf $realLocalPath", $output, $exitCode);
    if ($exitCode !== 0) {
      throw new StaticSuiteException("Unable to delete $realLocalPath:\n" . implode("\n", $output));
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getLogFilePath(string $taskId = NULL): string {
    return $this->dir . '/' . self::METADATA_DIR . '/' . ($taskId ?: $this->getDefaultTaskId()) . '.log';
  }

  /**
   * {@inheritdoc}
   */
  public function getFiles(): array {
    $iterator = new RecursiveIteratorIterator(
      new RecursiveDirectoryIterator($this->getDir())
    );

    $files = [];
    foreach ($iterator as $file) {
      if ($file->isDir()) {
        continue;
      }
      $files[] = str_replace($this->getDir() . '/', '', $file->getPathname());
    }

    sort($files);

    return $files;
  }

}
