<?php

namespace Drupal\static_suite\Utility;

/**
 * Interface for misc utilities for Static Suite.
 *
 * Most methods could be declared as static, but that would make impossible
 * to override this service or decorate it.
 */
interface StaticSuiteUtilsInterface {

  /**
   * Get a formatted micro date.
   *
   * @param string $format
   *   Date format.
   * @param float $time
   *   Optional UNIX timestamp with microseconds in float format, as returned by
   *   microtime(TRUE).
   *
   * @return string
   *   A formatted micro date.
   */
  public function getFormattedMicroDate(string $format, float $time = NULL): string;

  /**
   * Tells whether we are being executed on CLI.
   *
   * @return bool
   *   True if running on CLI.
   */
  public function isRunningOnCli(): bool;

  /**
   * Tells whether command is being executed on an interactive TTY.
   *
   * @return bool
   *   True if running on an interactive TTY.
   */
  public function isInteractiveTty(): bool;

  /**
   * Tell whether any of the items matches a Regular Expression.
   *
   * @param array $items
   *   Array with string items to test.
   * @param array $regExpList
   *   Array with Regular Expressions to execute.
   *
   * @return bool
   *   True if any of the strings matches any Regular Expression,
   *   false otherwise
   */
  public function isAnyItemMatchingRegexpList(array $items, array $regExpList): bool;

  /**
   * Removes dot segments as per RFC 3986.
   *
   * @see http://tools.ietf.org/html/rfc3986#section-5.2.4
   *
   * @param string $input
   *   Input to be sanitized.
   *
   * @return string
   *   The input without dot segments.
   */
  public function removeDotSegments(string $input): string;

}
