<?php

namespace Drupal\static_suite\Lock;

use Drupal\Core\Lock\LockBackendInterface;

/**
 * An interface for a helper of the lock system.
 *
 * @ingroup lock
 */
interface LockHelperInterface {

  /**
   * Get the current lock backend.
   *
   * @return \Drupal\Core\Lock\LockBackendInterface
   *   The lock backend instance.
   */
  public function getLock(): LockBackendInterface;

  /**
   * Acquires a lock or waits until it can be acquired.
   *
   * Tries to acquire a lock. If that fails, wait a short period and try again.
   *
   * @param string $name
   *   Lock name. Limit of name's length is 255 characters.
   * @param float $timeout
   *   (optional) Lock lifetime in seconds. Defaults to 30.0.
   * @param int $delay
   *   Seconds to wait before trying to acquire a lock again. Defaults to 30.
   *
   * @return bool
   *   True if the lock has been acquired, or false otherwise.
   */
  public function acquireOrWait(string $name, float $timeout = 30.0, int $delay = 30): bool;

}
