<?php

namespace Drupal\static_suite\Release\Task;

use Drupal\Core\File\FileSystemInterface;

/**
 * A class that creates Task objects.
 */
class TaskFactory implements TaskFactoryInterface {

  /**
   * {@inheritdoc}
   */
  public function create(FileSystemInterface $fileSystem, string $dir, string $id): TaskInterface {
    return new Task($fileSystem, $dir, $id);
  }

}
