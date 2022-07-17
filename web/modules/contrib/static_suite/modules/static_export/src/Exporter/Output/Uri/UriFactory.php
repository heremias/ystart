<?php

namespace Drupal\static_export\Exporter\Output\Uri;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\static_suite\Security\FilePathSanitizerInterface;

/**
 * A factory to create UriInterface objects.
 */
class UriFactory implements UriFactoryInterface {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The file path sanitizer.
   *
   * @var \Drupal\static_suite\Security\FilePathSanitizerInterface
   */
  protected $filePathSanitizer;

  /**
   * UriFactory constructor.
   *
   * @param \Drupal\static_suite\Security\FilePathSanitizerInterface $filePathSanitizer
   *   The file path sanitizer.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   */
  public function __construct(FilePathSanitizerInterface $filePathSanitizer, ConfigFactoryInterface $configFactory) {
    $this->filePathSanitizer = $filePathSanitizer;
    $this->configFactory = $configFactory;
  }

  /**
   * {@inheritdoc}
   */
  public function create(string $target): UriInterface {
    return new Uri($this->configFactory->get('static_export.settings')
      ->get('uri.scheme'), $this->filePathSanitizer->sanitizePath(trim($target, '\/')));
  }

}
