<?php

namespace Drupal\static_suite\Release;

use Drupal\Core\File\FileSystemInterface;
use Drupal\static_suite\Release\Task\TaskFactoryInterface;

/**
 * An interface for factories that create ReleaseInterface objects.
 */
interface ReleaseFactoryInterface {

  /**
   * Creates a release object.
   *
   * @param \Drupal\Core\File\FileSystemInterface $fileSystem
   *   File system service.
   * @param string $allReleasesDir
   *   Dir where releases are stored.
   * @param string $uniqueId
   *   A unique id identifying this release.
   * @param \Drupal\static_suite\Release\Task\TaskFactoryInterface $taskFactory
   *   A task factory for Release.
   *
   * @return \Drupal\static_suite\Release\ReleaseInterface
   *   A release object.
   */
  public function create(FileSystemInterface $fileSystem, string $allReleasesDir, string $uniqueId, TaskFactoryInterface $taskFactory): ReleaseInterface;

}
