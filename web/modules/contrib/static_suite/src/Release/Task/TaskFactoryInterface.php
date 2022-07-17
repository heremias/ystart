<?php

namespace Drupal\static_suite\Release\Task;

use Drupal\Core\File\FileSystemInterface;

/**
 * An interface for factories that create TaskInterface objects.
 */
interface TaskFactoryInterface {

  /**
   * Creates a task object.
   *
   * @param \Drupal\Core\File\FileSystemInterface $fileSystem
   *   Drupal file system service.
   * @param string $dir
   *   Path to the directory where task info is stored.
   * @param string $id
   *   Task id.
   *
   * @return \Drupal\static_suite\Release\Task\TaskInterface
   *   A task object.
   */
  public function create(FileSystemInterface $fileSystem, string $dir, string $id): TaskInterface;

}
