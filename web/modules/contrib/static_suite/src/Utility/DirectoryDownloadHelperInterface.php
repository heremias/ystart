<?php

namespace Drupal\static_suite\Utility;

use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * An interface for directory download helpers.
 */
interface DirectoryDownloadHelperInterface {

  /**
   * Extension for compressed files.
   */
  public const COMPRESSED_EXTENSION = 'tar.gz';

  /**
   * Extension for uncompressed files.
   */
  public const UNCOMPRESSED_EXTENSION = 'tar';

  /**
   * Controller to download all files in a directory, optionally compressed.
   *
   * @param string $dir
   *   Absolute path to the directory to be downloaded.
   * @param string|null $filename
   *   Optional name for the file to be downloaded.
   * @param bool $compress
   *   Optional flag to tell whether the resulting file should be compressed.
   *   Only GZIP is supported.
   *
   * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
   *   The transferred file as response.
   */
  public function download(string $dir, string $filename = NULL, bool $compress = TRUE): BinaryFileResponse;

}
