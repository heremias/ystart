<?php

namespace Drupal\static_export_stream_wrapper_git\StreamWrapper;

use Drupal\Core\StreamWrapper\LocalStream;
use Drupal\Core\StreamWrapper\StreamWrapperInterface;
use Drupal\Core\Url;

/**
 * Defines a Git File System (static-git://) stream wrapper for Static Export.
 *
 * Due to the fact that we need to commit and push all files from a
 * FileCollection at once, this wrapper is, in fact, a local wrapper accompanied
 * by an event subscriber which is in charge of commiting and pushing to the
 * remote repository.
 */
class GitStream extends LocalStream {

  /**
   * Config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * GitStream constructor.
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
    return t('Git File System for Static Export');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return t('A Git-based file system to store data exported by Static Export.');
  }

  /**
   * {@inheritdoc}
   */
  public function getDirectoryPath() {
    return $this->configFactory->get('static_export_stream_wrapper_git.settings')
      ->get('repo_dir') . $this->configFactory->get('static_export_stream_wrapper_git.settings')
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
