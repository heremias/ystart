<?php

namespace Drupal\static_suite\Utility;

use DateTime;

/**
 * Interface for helper utilities for unique IDs.
 */
interface UniqueIdHelperInterface {

  /**
   * Generates or gets a unique id for an exporter process.
   *
   * Generates a new unique id, or returns the previously generated one if
   * present.
   *
   * @return string
   *   A unique id.
   */
  public function getUniqueId(): string;

  /**
   * Generates a unique id for an exporter process.
   *
   * @return string
   *   A unique id.
   */
  public function generateUniqueId(): string;

  /**
   * Get default unique id.
   *
   * Returns a epoch unique id.
   *
   * @return string
   *   A unique id.
   */
  public function getDefaultUniqueId(): string;

  /**
   * Check it this is a unique id.
   *
   * @param string $uniqueId
   *   A unique id to be checked.
   *
   * @return bool
   *   True if it's a unique id.
   */
  public function isUniqueId($uniqueId): bool;

  /**
   * Get a DateTime object from a unique id.
   *
   * @param string $uniqueId
   *   A unique id.
   *
   * @return \DateTime
   *   A DateTime object.
   *
   * @throws \Drupal\static_suite\StaticSuiteException
   */
  public function getDateFromUniqueId(string $uniqueId): DateTime;

}
