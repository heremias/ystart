<?php

namespace Drupal\static_suite\Security;

/**
 * File path sanitizer.
 *
 * Sanitizes URIs that are file paths.
 */
class FilePathSanitizer extends FilePathSanitizerBase {

  /**
   * {@inheritdoc}
   *
   * It allows:
   *   - everything from sanitizePathSegment()
   *   - slashes (/)
   */
  public function sanitizePath(string $path): string {
    $path = parent::sanitizePath($path);

    $pathSegments = explode(DIRECTORY_SEPARATOR, $path);
    foreach ($pathSegments as $inx => $pathSegment) {
      $pathSegments[$inx] = $this->sanitizePathSegment($pathSegment);
    }
    $path = implode(DIRECTORY_SEPARATOR, $pathSegments);

    $path = str_replace('.' . DIRECTORY_SEPARATOR, '/', $path);
    $path = (string) preg_replace("/\/{2,}/", "/", $path);
    $path = (string) preg_replace("/\.{2,}/", "", $path);
    return $path;
  }

  /**
   * {@inheritdoc}
   *
   * It allows:
   *   - lowercase letters
   *   - numbers
   *   - hyphens (-)
   *   - dashes (_)
   *   - dots (.)
   */
  public function sanitizePathSegment(string $pathSegment): string {
    $pathSegment = parent::sanitizePathSegment($pathSegment);

    // This regexp explicitly blocks "%" chars that can be used to traverse
    // paths (%2e%2e%2f = ../)
    // @see https://owasp.org/www-community/attacks/Path_Traversal
    // strtolower() is called in parent.
    $pathSegment = (string) preg_replace("/([^a-zA-Z0-9-_\/.]+)/", "", $pathSegment);
    $pathSegment = (string) preg_replace("/\.{2,}/", ".", $pathSegment);
    return $pathSegment;
  }

  /**
   * {@inheritdoc}
   *
   * Alias of sanitizePathSegment() because in this implementation, path
   * segments and file names follow the same rules.
   */
  public function sanitizeFilename(string $filename): string {
    return $this->sanitizePathSegment($filename);
  }

  /**
   * {@inheritdoc}
   *
   * It allows:
   *   - lowercase letters
   *   - numbers.
   */
  public function sanitizeExtension(string $extension): string {
    $extension = parent::sanitizeExtension($extension);
    return (string) preg_replace("/([^a-zA-Z0-9]+)/", "", $extension);
  }

}
