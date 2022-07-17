<?php

namespace Drupal\static_export\File\MimeType;

use Symfony\Component\Mime\MimeTypeGuesserInterface;

/**
 * Guess the MIME type of a file by reading its contents.
 *
 * It supports the tree formats offered by Static Export: JSON, XML and YAML.
 */
class ContentMimeTypeGuesser implements MimeTypeGuesserInterface {

  /**
   * {@inheritdoc}
   */
  public function isGuesserSupported(): bool {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function guessMimeType($path): ?string {
    $content = @file_get_contents($path);
    if ($content) {
      $content = trim($content);
      if ($content !== '') {
        if ($content[0] === '{' && substr($content, -1) === '}') {
          return "application/json";
        }

        if (strpos($content, '<?xml') === 0) {
          return "text/xml";
        }

        if (strpos($content, '---') === 0) {
          return "text/yaml";
        }
      }
    }

    return NULL;
  }

}
