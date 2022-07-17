<?php

namespace Drupal\static_suite\Release;

use Drupal\static_suite\Release\Task\TaskSupervisorInterface;

/**
 * An interface for defining how to manage releases.
 */
interface ReleaseManagerInterface {

  public const CURRENT_RELEASE_NAME = 'current';

  public const RELEASES_DIRNAME = 'releases';

  /**
   * Init the release manager.
   *
   * This should be called only from a plugin, to avoid having different parts
   * that decide about $baseDir.
   *
   * @param string $baseDir
   *   An absolute path where "current" symlink and "releases" folder exists.
   *
   * @return ReleaseManagerInterface
   *   The initialized release manager.
   *
   * @throws \Drupal\static_suite\StaticSuiteException
   */
  public function init(string $baseDir): ReleaseManagerInterface;

  /**
   * Tells whether this manager has been initialized.
   *
   * @return bool
   *   True if it is initialized.
   */
  public function isInitialized(): bool;

  /**
   * Create all releases dir.
   *
   * @return bool
   *   True on success.
   *
   * @throws \Drupal\static_suite\StaticSuiteException
   */
  public function createReleasesDir(): bool;

  /**
   * Tell whether a release is current.
   *
   * @param string $uniqueId
   *   A release unique id.
   *
   * @return bool
   *   True if it's current.
   */
  public function isCurrent(string $uniqueId): bool;

  /**
   * Get current release instance.
   *
   * @return \Drupal\static_suite\Release\ReleaseInterface|null
   *   The current release.
   */
  public function getCurrentRelease(): ?ReleaseInterface;

  /**
   * Get current release's unique id from its symlink.
   *
   * @return string|null
   *   Current release's unique id.
   */
  public function getCurrentUniqueId(): ?string;

  /**
   * Get path to current pointer.
   *
   * @return string
   *   Current pointer path.
   */
  public function getCurrentPointerPath(): string;

  /**
   * Get $baseDir, where current and releases directories are stored.
   *
   * @return string
   *   The directory where current and releases directories are stored.
   */
  public function getBaseDir(): string;

  /**
   * Get directory where releases are stored inside $baseDir.
   *
   * @return string
   *   The directory where releases are stored.
   */
  public function getReleasesDir(): string;

  /**
   * Create a release.
   *
   * @param string $uniqueId
   *   A unique Id identifying a release.
   *
   * @return \Drupal\static_suite\Release\ReleaseInterface
   *   A new release instance.
   * @todo consider renaming this to bootstrap()
   */
  public function create(string $uniqueId): ReleaseInterface;

  /**
   * Delete a release.
   *
   * @param string $uniqueId
   *   A unique Id identifying a release.
   *
   * @return bool
   *   True on success.
   *
   * @throws \Drupal\static_suite\StaticSuiteException
   */
  public function delete(string $uniqueId): bool;

  /**
   * Publish a release.
   *
   * @param string $uniqueId
   *   A unique Id identifying a release.
   * @param string $taskId
   *   Id of the task that is checked to ensure is done before publishing it.
   *
   * @return bool
   *   True on success.
   *
   * @throws \Drupal\static_suite\StaticSuiteException
   */
  public function publish(string $uniqueId, string $taskId): bool;

  /**
   * Get a list of all releases.
   *
   * @return \Drupal\static_suite\Release\ReleaseInterface[]
   *   An array of Releases.
   */
  public function getAllReleases(): array;

  /**
   * Get a release by its unique ID.
   *
   * @param string $uniqueId
   *   The id of the release.
   *
   * @return \Drupal\static_suite\Release\ReleaseInterface|null
   *   A release object, or NULL if release is not present
   */
  public function getRelease(string $uniqueId): ?ReleaseInterface;

  /**
   * Delete old releases.
   *
   * @return bool
   *   True on success.
   *
   * @throws \Drupal\static_suite\StaticSuiteException
   */
  public function deleteOldReleases(): bool;

  /**
   * Tell whether a release exists.
   *
   * @param string $uniqueId
   *   A unique Id identifying a release.
   *
   * @return bool
   *   True if it exists.
   */
  public function exists(string $uniqueId): bool;

  /**
   * Get the latest release.
   *
   * @return \Drupal\static_suite\Release\ReleaseInterface|null
   *   The latest release.
   */
  public function getLatestRelease(): ?ReleaseInterface;

  /**
   * Validates that directory structure is correct.
   *
   * Validates everything managed by this interface, inside
   * [base_dir]/[live|preview]. There is a similar method inside
   * StaticBuilderInterface::validateDirStructure() that checks the same for
   * everything managed by StaticBuilderInterface.
   *
   * @return array
   *   Array of translated errors if any.
   */
  public function validateDirStructure(): array;

  /**
   * Get task supervisor.
   *
   * @return \Drupal\static_suite\Release\Task\TaskSupervisorInterface
   *   The task supervisor
   */
  public function getTaskSupervisor(): TaskSupervisorInterface;

}
