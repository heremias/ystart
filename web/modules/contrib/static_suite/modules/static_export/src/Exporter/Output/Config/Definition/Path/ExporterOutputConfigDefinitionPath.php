<?php

namespace Drupal\static_export\Exporter\Output\Config\Definition\Path;

use Drupal\static_suite\Security\FilePathSanitizerInterface;
use Drupal\static_suite\StaticSuiteUserException;

/**
 * Definition of the output of a exporter.
 */
class ExporterOutputConfigDefinitionPath implements ExporterOutputConfigDefinitionPathInterface {

  /**
   * @var \Drupal\static_suite\Security\FilePathSanitizerInterface
   */
  protected $filePathSanitizer;

  /**
   * Export directory.
   *
   * @var string
   */
  protected $dir;

  /**
   * Export filename.
   *
   * @var string
   */
  protected $filename;

  /**
   * Export extension.
   *
   * @var string
   */
  protected $extension;

  /**
   * Exporter output config definition constructor.
   *
   * This class is meant to be instantiated using
   * ExporterOutputConfigFactoryInterface::create() method and shouldn't be
   * manually instantiated.
   *
   * @param \Drupal\static_suite\Security\FilePathSanitizerInterface $filePathSanitizer
   *   The file path sanitizer.
   * @param string $dir
   *   The export directory, relative to base dir inside data dir. It can
   *   contain subdirectories.
   *   Optional, it can be an empty string. It cannot be null, because we want
   *   to maintain the natural order of elements in a path
   *   ($dir/$filename.$extension) and defining an optional parameter before a
   *   required one ($filename) is bad practice.
   *   Hence, if an empty string is passed, we consider it to be null.
   * @param string $filename
   *   Export filename, mandatory.
   * @param string|null $extension
   *   Export extension. Optional, it can be null or an empty string.
   *
   * @throws \Drupal\static_suite\StaticSuiteUserException
   * @see ExporterOutputConfigDefinitionPathInterface::setExtension();
   *
   * @see ExporterOutputConfigDefinitionPathInterface::setFilename();
   */
  public function __construct(FilePathSanitizerInterface $filePathSanitizer, string $dir, string $filename, string $extension = NULL) {
    $this->filePathSanitizer = $filePathSanitizer;
    $this->setDir($dir);
    $this->setFilename($filename);
    $this->setExtension($extension);
  }

  /**
   * {@inheritdoc}
   */
  public function getDir(): ?string {
    return $this->dir;
  }

  /**
   * {@inheritdoc}
   */
  public function setDir(string $dir): ExporterOutputConfigDefinitionPathInterface {
    $sanitizedDir = $dir ? $this->filePathSanitizer->sanitizePath($dir) : NULL;
    $this->dir = empty($sanitizedDir) ? NULL : trim($sanitizedDir, '\/');
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getFilename(): string {
    return $this->filename;
  }

  /**
   * {@inheritdoc}
   **/
  public function setFilename(string $filename): ExporterOutputConfigDefinitionPathInterface {
    $sanitizeFilename = $this->filePathSanitizer->sanitizeFilename($filename);
    if (!preg_match("/([a-zA-Z0-9]+)/", $sanitizeFilename)) {
      throw new StaticSuiteUserException('Filename must contain letters or numbers, with a minimum length of one character.');
    }
    $this->filename = $sanitizeFilename;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getExtension(): ?string {
    return $this->extension;
  }

  /**
   * {@inheritdoc}
   */
  public function setExtension(string $extension = NULL): ExporterOutputConfigDefinitionPathInterface {
    $sanitizedExtension = $extension ? $this->filePathSanitizer->sanitizeExtension($extension) : NULL;
    $this->extension = empty($sanitizedExtension) ? NULL : $sanitizedExtension;
    return $this;
  }

}
