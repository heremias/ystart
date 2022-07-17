<?php

namespace Drupal\static_suite\Utility;

use Drupal\Core\Archiver\ArchiveTar;
use Drupal\Core\File\Exception\FileException;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Drupal\system\FileDownloadController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Directory download helper.
 *
 * Provides a helper to download a directory from a controller.
 */
class DirectoryDownloadHelper implements DirectoryDownloadHelperInterface {

  /**
   * The file download controller.
   *
   * @var \Drupal\system\FileDownloadController
   */
  protected FileDownloadController $fileDownloadController;

  /**
   * The file system.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected FileSystemInterface $fileSystem;

  /**
   * DirCompressAndDownloadHelper constructor.
   *
   * @param \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface $streamWrapperManager
   *   The StreamWrapper manager.
   * @param \Drupal\Core\File\FileSystemInterface $fileSystem
   *   The file system.
   */
  public function __construct(
    StreamWrapperManagerInterface $streamWrapperManager,
    FileSystemInterface $fileSystem
  ) {
    $this->fileDownloadController = new FileDownloadController($streamWrapperManager);
    $this->fileSystem = $fileSystem;
  }

  /**
   * {@inheritdoc}
   */
  public function download(string $dir, string $filename = NULL, bool $compress = TRUE): BinaryFileResponse {
    $filename = $filename ?: basename($dir);
    $extension = $compress ? DirectoryDownloadHelperInterface::COMPRESSED_EXTENSION : DirectoryDownloadHelperInterface::UNCOMPRESSED_EXTENSION;
    $path = $this->fileSystem->getTempDirectory() . '/' . $filename . '.' . $extension;
    try {
      $this->fileSystem->delete($path);
    }
    catch (FileException) {
      // Ignore failed deletes.
    }

    $archiver = new ArchiveTar($path, $compress ? 'gz' : NULL);
    $archiver->addModify([$dir], '', $dir);
    $request = new Request(['file' => $filename . '.' . $extension]);
    return $this->fileDownloadController->download($request, 'temporary');
  }

}
