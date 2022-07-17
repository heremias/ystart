<?php

namespace Drupal\static_export_stream_wrapper_local\StreamWrapper;

use Drupal\Core\StreamWrapper\LocalStream;
use Drupal\Core\StreamWrapper\StreamWrapperInterface;
use Drupal\Core\Url;

/**
 * Defines a local static export (static-local://) stream wrapper class.
 *
 * Files are saved in local file system.
 */
class LocalFileSystemStream extends LocalStream {

  /**
   * Config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructor.
   *
   * Dependency injection will not work here, since PHP doesn't give us a
   * chance to perform the injection. PHP creates the stream wrapper objects
   * automatically when certain file functions are called. Therefore we'll use
   * the \Drupal service locator.
   */
  public function __construct() {
    // phpcs:ignore
    $this->configFactory = \Drupal::service('config.factory');
  }

  /**
   * {@inheritdoc}
   */
  public static function getType() {
    return StreamWrapperInterface::LOCAL_NORMAL;
  }

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return t('Static Export local files');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return t('Files exported by Static Export module to local file system.');
  }

  /**
   * {@inheritdoc}
   */
  public function getDirectoryPath() {
    return $this->configFactory->get('static_export_stream_wrapper_local.settings')
      ->get('data_dir');
  }

  /**
   * {@inheritdoc}
   */
  public function getExternalUrl() {
    $path = str_replace('\\', '/', $this->getTarget());
    return Url::fromRoute('static_export.file_viewer', ['uri_target' => $path], [
      'absolute' => TRUE,
      'path_processing' => FALSE,
    ])->toString();
  }

}
