<?php

namespace Drupal\static_export\Exporter\Output\Uri;

/**
 * A class for URI objects used by exporters.
 */
class Uri implements UriInterface {

  /**
   * URI scheme.
   *
   * @var string
   */
  protected $scheme;

  /**
   * URI target.
   *
   * @var string
   */
  protected $target;

  /**
   * URI constructor.
   *
   * This class is meant to be instantiated using
   * ExporterOutputConfigFactoryInterface::create() method and shouldn't be
   * manually instantiated.
   *
   * @param string $scheme
   *   URI scheme, a string without ";//".
   * @param string $target
   *   URI target, a string without leading slash.
   */
  public function __construct(string $scheme, string $target) {
    $this->scheme = str_replace('://', '', $scheme);
    $this->target = $target;
  }

  /**
   * {@inheritdoc}
   */
  public function getScheme(): string {
    return $this->scheme;
  }

  /**
   * {@inheritdoc}
   */
  public function getTarget(): string {
    return $this->target;
  }

  /**
   * {@inheritdoc}
   */
  public function getComposed(): string {
    return $this->scheme . '://' . $this->target;
  }

  /**
   * {@inheritdoc}
   */
  public function __toString(): string {
    return $this->getComposed();
  }

}
