<?php

namespace Drupal\static_export\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Drupal\static_suite\Utility\DirectoryDownloadHelperInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * ExportDataDownloadController controller.
 *
 * Provides a controller for compressing and downloading the data directory of
 * Static Export.
 */
class ExportDataDownloadController extends ControllerBase {

  /**
   * Exported data filename.
   */
  public const FILE_NAME = 'static-export-data';

  /**
   * The stream wrapper manager.
   *
   * @var \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface
   */
  protected StreamWrapperManagerInterface $streamWrapperManager;

  /**
   * The directory download helper.
   *
   * @var \Drupal\static_suite\Utility\DirectoryDownloadHelperInterface
   */
  protected DirectoryDownloadHelperInterface $directoryDownloadHelper;

  /**
   * StaticExportDownload constructor.
   *
   * @param \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface $streamWrapperManager
   *   The stream wrapper manager.
   * @param \Drupal\static_suite\Utility\DirectoryDownloadHelperInterface $directoryDownloadHelper
   *   The directory download helper.
   */
  public function __construct(StreamWrapperManagerInterface $streamWrapperManager, DirectoryDownloadHelperInterface $directoryDownloadHelper) {
    $this->streamWrapperManager = $streamWrapperManager;
    $this->directoryDownloadHelper = $directoryDownloadHelper;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get("stream_wrapper_manager"),
      $container->get("static_suite.directory_download_helper"),
    );
  }

  /**
   * Controller to download all files in a directory, compressed as tar.gz.
   *
   * @param string $scheme
   *   Schema name.
   *
   * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
   *   The transferred file as response.
   */
  public function download(string $scheme): BinaryFileResponse {
    $streamWrapper = $this->streamWrapperManager->getViaScheme($scheme);
    if (!$streamWrapper) {
      throw new UnprocessableEntityHttpException('No stream wrapper available for scheme "' . $scheme . '://"');
    }
    $dir = $streamWrapper->realpath();
    if (!$dir) {
      throw new NotFoundHttpException();
    }
    return $this->directoryDownloadHelper->download($dir, self::FILE_NAME);
  }

}
