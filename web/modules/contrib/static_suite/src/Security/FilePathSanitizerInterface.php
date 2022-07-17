<?php

namespace Drupal\static_suite\Security;

/**
 * Interface for a File Path sanitizer.
 *
 * Strictly speaking, a file path is not an URI, since scheme can be omitted for
 * file paths. But the URL specification (https://url.spec.whatwg.org/) defines
 * a "path-relative-scheme-less-URL".
 *
 * To be able to better deal with file paths, this interface defines a file name
 * and a extension sanitizer.
 *
 * Sanitizes all components of a File Path.
 *
 * @see https://url.spec.whatwg.org/
 */
interface FilePathSanitizerInterface extends UriSanitizerInterface {

  /**
   * Sanitizes a filename.
   *
   * @param string $filename
   *   Filename to be sanitized.
   *
   * @return string
   *   Sanitized filename.
   */
  public function sanitizeFilename(string $filename): string;

  /**
   * Sanitizes a file extension.
   *
   * @param string $extension
   *   Extension to be sanitized.
   *
   * @return string
   *   Sanitized extension.
   */
  public function sanitizeExtension(string $extension): string;

}
