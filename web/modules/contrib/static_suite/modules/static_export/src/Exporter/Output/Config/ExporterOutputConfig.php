<?php

namespace Drupal\static_export\Exporter\Output\Config;

use Drupal\static_export\Exporter\Output\Config\Definition\ExporterOutputConfigDefinitionInterface;
use Drupal\static_export\Exporter\Output\Uri\UriFactoryInterface;
use Drupal\static_export\Exporter\Output\Uri\UriInterface;
use Drupal\static_suite\Security\FilePathSanitizerInterface;

/**
 * Defines the output of a exporter.
 */
class ExporterOutputConfig implements ExporterOutputConfigInterface {

  /**
   * @var \Drupal\static_suite\Security\FilePathSanitizerInterface
   */
  protected $filePathSanitizer;

  /**
   * The URI factory.
   *
   * @var \Drupal\static_export\Exporter\Output\Uri\UriFactoryInterface
   */
  protected $uriFactory;

  /**
   * Base directory where all files from this type are stored (entity, config,
   * etc)
   *
   * It's intentionally private to avoid being altered from classes extending
   * this one.
   *
   * @var string
   */
  private $baseDir;

  /**
   * The configuration definition.
   *
   * @var \Drupal\static_export\Exporter\Output\Config\Definition\ExporterOutputConfigDefinitionInterface
   */
  protected $definition;

  /**
   * Exporter output config constructor.
   *
   * This class is used by all exporters, so it's quite permissive in its
   * parameters and only requires a.
   *
   * @param \Drupal\static_suite\Security\FilePathSanitizerInterface $filePathSanitizer
   *   The file path sanitizer.
   * @param \Drupal\static_export\Exporter\Output\Uri\UriFactoryInterface $uriFactory
   *   The URI factory.
   * @param string $baseDir
   *   Base directory (entity, config, etc)
   * @param \Drupal\static_export\Exporter\Output\Config\Definition\ExporterOutputConfigDefinitionInterface $definition
   *   The configuration definition.
   */
  public function __construct(FilePathSanitizerInterface $filePathSanitizer, UriFactoryInterface $uriFactory, string $baseDir, ExporterOutputConfigDefinitionInterface $definition) {
    $this->filePathSanitizer = $filePathSanitizer;
    $this->uriFactory = $uriFactory;
    $this->setBaseDir($baseDir);
    $this->definition = $definition;
  }

  /**
   * {@inheritdoc}
   *
   * This is where the output of exporters is defined as follows:
   * [LANGCODE]/[BASE_DIR]/[DIR]/[BASENAME].[EXTENSION]
   */
  public function uri(): UriInterface {
    $extension = $this->getDefinition()->getPath()->getExtension();
    $filename = $this->getDefinition()->getPath()->getFilename();
    $langcode = $this->getDefinition()->getLanguage()->getId();
    $target = implode('/',
      [
        $langcode,
        $this->baseDir,
        $this->getDefinition()->getPath()->getDir(),
        $extension ? $filename . '.' . $extension : $filename,
      ]
    );
    return $this->uriFactory->create($target);
  }

  /**
   * {@inheritdoc}
   */
  public function getBaseDir(): string {
    return $this->baseDir;
  }

  /**
   * {@inheritdoc}
   */
  public function setBaseDir(string $baseDir = NULL): ExporterOutputConfigInterface {
    $this->baseDir = $baseDir ? $this->filePathSanitizer->sanitizePath($baseDir) : NULL;
    return $this;
  }

  /**
   * {@inheritdoc}
   *
   * @return \Drupal\static_export\Exporter\Output\Config\Definition\ExporterOutputConfigDefinitionInterface
   *   The configuration definition.
   */
  public function getDefinition(): ExporterOutputConfigDefinitionInterface {
    return $this->definition;
  }

}
