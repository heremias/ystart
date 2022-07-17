<?php

namespace Drupal\static_suite\Release;

use Drupal\Core\File\FileSystemInterface;
use Drupal\static_suite\Release\Task\TaskFactoryInterface;

/**
 * A class that creates Release objects.
 */
class ReleaseFactory implements ReleaseFactoryInterface {

  /**
   * Release cache to avoid creating different instances of the same release.
   *
   * @var \Drupal\static_suite\Release\ReleaseInterface[]
   */
  protected $releaseCache;

  /**
   * {@inheritdoc}
   */
  public function create(FileSystemInterface $fileSystem, string $allReleasesDir, string $uniqueId, TaskFactoryInterface $taskFactory): ReleaseInterface {
    if (empty($this->releaseCache[$allReleasesDir][$uniqueId])) {
      $this->releaseCache[$allReleasesDir][$uniqueId][get_class($taskFactory)] = new Release($fileSystem, $allReleasesDir, $uniqueId, $taskFactory);
    }
    return $this->releaseCache[$allReleasesDir][$uniqueId][get_class($taskFactory)];
  }

}
