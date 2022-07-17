<?php

namespace Drupal\static_export\Exporter\Output\Uri;

/**
 * An interface for URI objects used by exporters.
 */
interface UriInterface {

  /**
   * Get URI scheme.
   *
   * This scheme should be obtained from Drupal configuration and not manually
   * defined.
   *
   * @return string
   *   The URI scheme (e.g.- "static://", "s3://")
   */
  public function getScheme(): string;

  /**
   * Returns the local writable target of the resource within the stream.
   *
   * @return string
   *   The URI target (e.g.- "path/to/file.ext")
   */
  public function getTarget(): string;

  /**
   * Composes a URI string from its components: scheme + '://' + target.
   *
   * @return string
   *   The URI (e.g.- "static://path/to/file.ext")
   */
  public function getComposed(): string;

  /**
   * Alias of getComposed()
   *
   * @return string
   *   The URI (e.g.- "static://path/to/file.ext")
   */
  public function __toString(): string;

}
