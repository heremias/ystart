<?php

namespace Drupal\static_suite\Security;

/**
 * Base class for all classes implementing FilePathSanitizerInterface.
 *
 * This class offers a working method for all members of the interface. This
 * way, sanitizers only have to implement the methods they are interested in.
 */
abstract class FilePathSanitizerBase extends UriSanitizerBase implements FilePathSanitizerInterface {

  /**
   * {@inheritdoc}
   */
  public function sanitizeFilename(string $filename): string {
    return $this->transliteration->transliterate($this->allowUppercase ? $filename : strtolower($filename));
  }

  /**
   * {@inheritdoc}
   */
  public function sanitizeExtension(string $extension): string {
    return $this->transliteration->transliterate($this->allowUppercase ? $extension : strtolower($extension));
  }

}
