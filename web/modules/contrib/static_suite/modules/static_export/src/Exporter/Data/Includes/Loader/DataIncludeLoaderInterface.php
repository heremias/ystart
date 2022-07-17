<?php

namespace Drupal\static_export\Exporter\Data\Includes\Loader;

use Drupal\static_export\Exporter\Output\Uri\UriInterface;

/**
 * Defines an interface for Data include loaders.
 */
interface DataIncludeLoaderInterface {

  /**
   * Load data includes for a URI.
   *
   * Find all includes in a URI's content and replace them.
   *
   * @param \Drupal\static_export\Exporter\Output\Uri\UriInterface $uri
   *   URI to be parsed.
   * @param string|null $mimeType
   *   Optional content mime type.
   *
   * @return string
   *   The processed string.
   */
  public function loadUri(UriInterface $uri, string $mimeType = NULL): string;

  /**
   * Load data includes for a string.
   *
   * Find all includes in a string and replace them.
   *
   * @param string $contents
   *   String to be parsed.
   * @param string|null $mimeType
   *   Optional content mime type.
   *
   * @return string
   *   The processed string.
   */
  public function loadString(string $contents, string $mimeType = NULL): string;

}
