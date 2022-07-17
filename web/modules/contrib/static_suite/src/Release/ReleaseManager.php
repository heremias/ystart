<?php

namespace Drupal\static_suite\Release;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\static_suite\Release\Task\TaskFactoryInterface;
use Drupal\static_suite\Release\Task\TaskSupervisorInterface;
use Drupal\static_suite\StaticSuiteException;
use Drupal\static_suite\Utility\UniqueIdHelperInterface;

/**
 * A class to manage releases.
 */
class ReleaseManager implements ReleaseManagerInterface {

  use StringTranslationTrait;

  /**
   * Release factory.
   *
   * @var \Drupal\static_suite\Release\ReleaseFactoryInterface
   */
  protected $releaseFactory;

  /**
   * Task factory.
   *
   * @var \Drupal\static_suite\Release\Task\TaskFactoryInterface
   */
  protected $taskFactory;

  /**
   * Task supervisor.
   *
   * @var \Drupal\static_suite\Release\Task\TaskSupervisorInterface
   */
  protected $taskSupervisor;

  /**
   * Unique ID helper.
   *
   * @var \Drupal\static_suite\Utility\UniqueIdHelperInterface
   */
  protected $uniqueIdHelper;

  /**
   * The directory where releases are managed.
   *
   * It's the dir that holds "current" symlink.
   *
   * @var string
   */
  protected $baseDir;

  /**
   * Drupal config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * A flag to know if this manager has been initialized.
   *
   * @var bool
   */
  protected $initialized = FALSE;

  /**
   * File system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * Release Manager constructor.
   *
   * Although this manager can be directly accessed and used, it should be
   * accessed through a plugin's getReleaseManager() method. This way, release
   * manager is already configured and initialized, honoring that plugin's
   * configuration. This is also essential to allow different plugins with
   * different base directories (a Work In Progress not already finished).
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Drupal config factory.
   * @param \Drupal\Core\File\FileSystemInterface $fileSystem
   *   File system service.
   * @param \Drupal\static_suite\Release\ReleaseFactoryInterface $release_factory
   *   Release factory.
   * @param \Drupal\static_suite\Release\Task\TaskFactoryInterface $task_factory
   *   Task factory.
   * @param \Drupal\static_suite\Release\Task\TaskSupervisorInterface $task_supervisor
   *   Task supervisor.
   * @param \Drupal\static_suite\Utility\UniqueIdHelperInterface $unique_id_helper
   *   Static Suite utils.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    FileSystemInterface $fileSystem,
    ReleaseFactoryInterface $release_factory,
    TaskFactoryInterface $task_factory,
    TaskSupervisorInterface $task_supervisor,
    UniqueIdHelperInterface $unique_id_helper
  ) {
    $this->configFactory = $config_factory;
    $this->fileSystem = $fileSystem;
    $this->releaseFactory = $release_factory;
    $this->taskFactory = $task_factory;
    $this->taskSupervisor = $task_supervisor;
    $this->taskSupervisor->setReleaseManager($this);
    $this->uniqueIdHelper = $unique_id_helper;
  }

  /**
   * {@inheritdoc}
   */
  public function init(string $baseDir): ReleaseManagerInterface {
    if ($this->baseDir !== $baseDir) {
      $this->baseDir = $baseDir;
      $this->createReleasesDir();
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isInitialized(): bool {
    return (bool) $this->baseDir;
  }

  /**
   * {@inheritdoc}
   */
  public function reset(): void {
    $this->initialized = FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function createReleasesDir(): bool {
    $releasesDir = $this->baseDir . '/' . self::RELEASES_DIRNAME;
    clearstatcache(TRUE);
    if (!is_dir($releasesDir)) {
      $result = $this->fileSystem->mkdir($releasesDir, 0777, TRUE);
      if ($result === FALSE) {
        throw new StaticSuiteException('Unable to create releases dir ' . $releasesDir);
      }
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function isCurrent(string $uniqueId): bool {
    return $this->getCurrentUniqueId() === $uniqueId;
  }

  /**
   * {@inheritdoc}
   */
  public function getCurrentRelease(): ?ReleaseInterface {
    $currentUniqueId = $this->getCurrentUniqueId();
    if ($currentUniqueId) {
      return $this->create($currentUniqueId);
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getCurrentUniqueId(): ?string {
    $currentUniqueId = NULL;
    $currentPointer = $this->getCurrentPointerPath();
    clearstatcache(TRUE);
    if (is_link($currentPointer)) {
      $currentReleasePath = readlink($currentPointer);
      $currentUniqueId = $this->fileSystem->basename($currentReleasePath);
    }
    return $currentUniqueId;
  }

  /**
   * {@inheritdoc}
   */
  public function getCurrentPointerPath(): string {
    return $this->baseDir . '/' . self::CURRENT_RELEASE_NAME;
  }

  /**
   * {@inheritdoc}
   */
  public function getBaseDir(): string {
    return $this->baseDir;
  }

  /**
   * {@inheritdoc}
   */
  public function getReleasesDir(): string {
    return $this->baseDir . '/' . self::RELEASES_DIRNAME;
  }

  /**
   * {@inheritdoc}
   */
  public function create(string $uniqueId): ReleaseInterface {
    return $this->releaseFactory->create($this->fileSystem, $this->getReleasesDir(), $uniqueId, $this->taskFactory);
  }

  /**
   * {@inheritdoc}
   */
  public function delete(string $uniqueId): bool {
    $release = $this->create($uniqueId);

    if ($this->isCurrent($uniqueId)) {
      throw new StaticSuiteException("Release $uniqueId is current. Cannot be deleted.");
    }

    return $release->deleteReleaseDir();
  }

  /**
   * {@inheritdoc}
   */
  public function publish(string $uniqueId, string $taskId): bool {
    $newRelease = $this->create($uniqueId);
    $currentRelease = $this->getCurrentRelease();

    // Check if current is the same as $uniqueId.
    if ($currentRelease && $currentRelease->uniqueId() === $uniqueId) {
      return TRUE;
    }

    // Check that $uniqueId is a real release.
    if (!$this->exists($uniqueId)) {
      // This throws because it's a race condition that never should happen.
      throw new StaticSuiteException("Cannot publish release $uniqueId. It doesn't exist.");
    }

    // Check that $uniqueId is built.
    if (!$newRelease->task($taskId)->isDone()) {
      // This throws because it's a race condition that never should happen.
      throw new StaticSuiteException("Cannot publish release $uniqueId. It's not built.");
    }

    $commands = [
      'cd ' . $this->baseDir,
      'rm -f ' . self::CURRENT_RELEASE_NAME,
      'ln -s ' . self::RELEASES_DIRNAME . "/$uniqueId " . self::CURRENT_RELEASE_NAME,
    ];
    $output = [];
    exec(implode(' && ', $commands), $output, $exitCode);
    if ($exitCode !== 0) {
      return FALSE;
    }

    // Don't blindly trust exitCode.
    // Check again that everything is as it should be.
    if ($this->isCurrent($uniqueId)) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getAllReleases(): array {
    $list = [];
    clearstatcache(TRUE);
    $dirs = glob($this->getReleasesDir() . '/*');
    foreach ($dirs as $dir) {
      clearstatcache(TRUE);
      if (is_dir($dir)) {
        $releaseUniqueId = $this->fileSystem->basename($dir);
        // Check that is a uniqueId.
        if ($this->uniqueIdHelper->isUniqueId($releaseUniqueId)) {
          $list[] = $this->create($releaseUniqueId);
        }
      }
    }
    // Sort list so new releases got first.
    rsort($list);
    return $list;
  }

  /**
   * {@inheritdoc}
   */
  public function getRelease(string $uniqueId): ?ReleaseInterface {
    $allReleases = $this->getAllReleases();
    foreach ($allReleases as $release) {
      if ($release->uniqueId() === $uniqueId) {
        return $release;
      }
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getTaskSupervisor(): TaskSupervisorInterface {
    return $this->taskSupervisor;
  }

  /**
   * {@inheritdoc}
   */
  public function deleteOldReleases(): bool {
    $allReleases = $this->getAllReleases();
    $numberOfReleasesToKeep = $this->configFactory->get('static_build.settings')
      ->get('number_of_releases_to_keep');
    /** @var \Drupal\static_suite\Release\Release[] $releasesToDelete */
    $releasesToDelete = array_slice($allReleases, $numberOfReleasesToKeep);
    foreach ($releasesToDelete as $release) {
      $this->delete($release->uniqueId());
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function exists(string $uniqueId): bool {
    $release = $this->create($uniqueId);
    $releaseDir = $release->getDir();
    clearstatcache(TRUE);
    return is_dir($releaseDir);
  }

  /**
   * {@inheritdoc}
   */
  public function getLatestRelease(): ?Release {
    $latestRelease = NULL;
    $allReleases = $this->getAllReleases();
    if (!empty($allReleases[0])) {
      $latestRelease = $allReleases[0];
    }
    return $latestRelease;
  }

  /**
   * {@inheritdoc}
   */
  public function validateDirStructure(): array {
    $errors = [];

    $currentPointer = $this->getCurrentPointerPath();
    if (file_exists($currentPointer)) {
      if (is_link($currentPointer)) {
        if (!is_writable($currentPointer)) {
          $errors[] = $this->t('The symlink %symlink exists but is not writable.', ['%symlink' => $currentPointer]);
        }
      }
      else {
        $errors[] = $this->t('The path %symlink exists but is not a symlink.', ['%symlink' => $currentPointer]);
      }
    }

    $releasesDir = $this->getReleasesDir();
    if (file_exists($releasesDir)) {
      if (is_dir($releasesDir)) {
        if (!is_writable($releasesDir)) {
          $errors[] = $this->t('The directory %directory exists but is not writable.', ['%directory' => $releasesDir]);
        }
      }
      else {
        $errors[] = $this->t('The path %directory exists but is not a directory.', ['%directory' => $releasesDir]);
      }
    }

    return $errors;
  }

}
