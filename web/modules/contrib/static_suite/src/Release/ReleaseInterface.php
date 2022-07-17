<?php

namespace Drupal\static_suite\Release;

use Drupal\static_suite\Release\Task\TaskInterface;

/**
 * An interface for releases.
 */
interface ReleaseInterface {

  public const METADATA_DIR = '.metadata';

  public const TASKS_DIR = self::METADATA_DIR . '/tasks';

  public const UNIQUE_ID_FILE_NAME = 'unique-id.txt';

  public const UNIQUE_ID_FILE = self::METADATA_DIR . '/' . self::UNIQUE_ID_FILE_NAME;

  /**
   * Adds a task to this release, or retrieve a previous one.
   *
   * @param string $id
   *   Task id.
   *
   * @return \Drupal\static_suite\Release\Task\TaskInterface
   *   A newly created task or a previous one.
   */
  public function task(string $id): TaskInterface;

  /**
   * Get this release dir.
   *
   * @return string
   *   This release's dir.
   */
  public function getDir(): string;

  /**
   * Get this release metadata dir.
   *
   * @return string
   *   This release's metadata dir.
   */
  public function getMetadataDir(): string;

  /**
   * Get this release tasks dir.
   *
   * @return string
   *   This release's task dir.
   */
  public function getTasksDir(): string;

  /**
   * Get unique id.
   *
   * @return string
   *   This release's unique id.
   */
  public function uniqueId(): string;

  /**
   * Create release dir.
   *
   * @return bool
   *   True on success.
   *
   * @throws \Drupal\static_suite\StaticSuiteException
   */
  public function createReleaseDir(): bool;

  /**
   * Delete release dir.
   *
   * @return bool
   *   True on success.
   *
   * @throws \Drupal\static_suite\StaticSuiteException
   */
  public function deleteReleaseDir(): bool;

  /**
   * Copy dirs and files to a dir inside a release dir.
   *
   * @param string $source
   *   It can be external or local. If external, it must be an absolute path.
   * @param string $localDestination
   *   An optional path, relative to release directory. Defaults to "".
   * @param array $excludedPaths
   *   An array of paths to be excluded from the copy.
   * @param bool $delete
   *   A flag to tell if, when copying a folder to another, the contents in the
   *   target folder should be deleted. Equivalent to the --delete option of
   *   rsync. False by default.
   *
   * @return bool
   *   True on success.
   *
   * @throws \Drupal\static_suite\StaticSuiteException
   */
  public function copyToDir(string $source, string $localDestination = '', array $excludedPaths = [], bool $delete = FALSE): bool;

  /**
   * Move dirs and files to a dir inside a release dir.
   *
   * @param string $source
   *   It can be external or local. If external, it must be an absolute path.
   * @param string $localDestination
   *   An optional path, relative to release directory. Defaults to "".
   *
   * @return bool
   *   True on success.
   *
   * @throws \Drupal\static_suite\StaticSuiteException
   */
  public function moveToDir(string $source, string $localDestination = ""): bool;

  /**
   * Delete all files which are direct descendants of a local directory.
   *
   * This method is aimed at deleting files only inside the release dir.
   * It's not recursive.
   *
   * @param string $localPath
   *   A local directory path inside the release dir.
   * @param array $excludes
   *   An optional array of files to be excluded from deletion.
   *
   * @return bool
   *   True on success.
   *
   * @throws \Drupal\static_suite\StaticSuiteException
   */
  public function deleteFiles(string $localPath, array $excludes = []): bool;

  /**
   * Delete all dirs which are direct descendants of a local directory.
   *
   * This method is aimed at deleting dirs only inside the release dir.
   *
   * @param string $localPath
   *   An internal directory path.
   * @param array $excludes
   *   An optional array of dirs to be excluded from deletion.
   *
   * @return bool
   *   True on success.
   *
   * @throws \Drupal\static_suite\StaticSuiteException
   */
  public function deleteDirs(string $localPath, array $excludes = []): bool;

  /**
   * Delete dirs and files inside the release directory.
   *
   * This method is aimed at deleting dirs/files only inside the release dir.
   *
   * @param string $localPath
   *   An internal directory path.
   *
   * @return bool
   *   True on success.
   *
   * @throws \Drupal\static_suite\StaticSuiteException
   */
  public function deleteDirsAndFiles(string $localPath): bool;

  /**
   * Get all files inside a release.
   *
   * @return array
   *   An array with all files relative to release directory.
   */
  public function getFiles(): array;

}
