<?php

namespace Drupal\static_suite\Utility;

use DateTime;

/**
 * Misc utilities for Static Export.
 */
class StaticSuiteUtils implements StaticSuiteUtilsInterface {

  /**
   * {@inheritdoc}
   */
  public function getFormattedMicroDate(string $format, float $time = NULL): string {
    if (!$time) {
      $time = microtime(TRUE);
    }
    $seconds = floor($time);
    $microSeconds = sprintf("%06d", ($time - floor($time)) * 1000000);
    $microDate = DateTime::createFromFormat('U.u', "$seconds.$microSeconds");
    return $microDate->format($format);
  }

  /**
   * {@inheritdoc}
   */
  public function isRunningOnCli(): bool {
    return PHP_SAPI === 'cli';
  }

  /**
   * {@inheritdoc}
   */
  public function isInteractiveTty(): bool {
    return posix_isatty(STDOUT);
  }

  /**
   * {@inheritdoc}
   */
  public function isAnyItemMatchingRegexpList(array $items, array $regExpList): bool {
    foreach ($items as $item) {
      foreach ($regExpList as $regExp) {
        if (preg_match("/$regExp/", $item)) {
          return TRUE;
        }
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function removeDotSegments(string $input): string {
    // 1.  The input buffer is initialized with the now-appended path
    //    components and the output buffer is initialized to the empty
    //     string.
    $output = '';

    // 2.  While the input buffer is not empty, loop as follows:
    while ($input !== '') {
      // A.  If the input buffer begins with a prefix of "`../`" or "`./`",
      //     then remove that prefix from the input buffer; otherwise,.
      if (
        ($prefix = substr($input, 0, 3)) === '../' ||
        ($prefix = substr($input, 0, 2)) === './'
      ) {
        $input = substr($input, strlen($prefix));
      }
      // B.  if the input buffer begins with a prefix of "`/./`" or "`/.`",
      // where "`.`" is a complete path segment, then replace that.
      else {
        // Prefix with "`/`" in the input buffer; otherwise,.
        if (
          ($prefix = substr($input, 0, 3)) === '/./' ||
          ($prefix = $input) === '/.'
        ) {
          $input = '/' . substr($input, strlen($prefix));
        }
        // C.  if the input buffer begins with a prefix of "/../" or "/..",.
        else {
          // Where "`..`" is a complete path segment, then replace that
          //     prefix with "`/`" in the input buffer and remove the last
          //     segment and its preceding "/" (if any) from the output
          //     buffer; otherwise,.
          if (
            ($prefix = substr($input, 0, 4)) === '/../' ||
            ($prefix = $input) === '/..'
          ) {
            $input = '/' . substr($input, strlen($prefix));
            $output = substr($output, 0, strrpos($output, '/'));
          }
          else {
            // D.  if the input buffer consists only of "." or "..", then remove
            //     that from the input buffer; otherwise,.
            if ($input === '.' || $input === '..') {
              $input = '';
            }
            // E.  move the first path segment in the input buffer to the end of
            // the output buffer, including the initial "/" character (if
            // any) and any subsequent characters up to, but not including,
            // the next "/" character or the end of the input buffer.
            else {
              $pos = strpos($input, '/');
              if ($pos === 0) {
                $pos = strpos($input, '/', $pos + 1);
              }
              if ($pos === FALSE) {
                $pos = strlen($input);
              }
              $output .= substr($input, 0, $pos);
              $input = (string) substr($input, $pos);
            }
          }
        }
      }
    }

    // 3.  Finally, the output buffer is returned as the result of remove_dot_segments.
    return $output;
  }

}
