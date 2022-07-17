<?php

namespace Drupal\static_export\Exporter\Output\Uri;

/**
 * An interface for factories that create UriInterface objects.
 */
interface UriFactoryInterface {

  /**
   * Creates a UriInterface object.
   *
   * @param string $target
   *   Uri target, a relative path without a leading slash.
   *
   * @return UriInterface
   *   An object implementing UriInterface.
   */
  public function create(string $target): UriInterface;

}
