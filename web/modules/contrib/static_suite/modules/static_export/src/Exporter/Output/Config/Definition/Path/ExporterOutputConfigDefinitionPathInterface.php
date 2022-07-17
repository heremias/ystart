<?php

namespace Drupal\static_export\Exporter\Output\Config\Definition\Path;

/**
 * An interface for paths used in the definition of output configurations.
 */
interface ExporterOutputConfigDefinitionPathInterface {

  /**
   * Get export directory.
   *
   * @return string|null
   *   The export directory, relative to base directory (entity, config, etc)
   *   inside data dir.
   */
  public function getDir(): ?string;

  /**
   * Set export directory.
   *
   * @param string $dir
   *   The export directory, relative to base dir inside data dir.
   *
   * @return ExporterOutputConfigDefinitionPathInterface
   *   This instance, to make this method chainable.
   */
  public function setDir(string $dir): ExporterOutputConfigDefinitionPathInterface;

  /**
   * Get export filename.
   *
   * @return string
   */
  public function getFilename(): string;

  /**
   * Set export filename.
   *
   * @param string $filename
   *   Export filename.
   *   It must be a string with letters or numbers, with a minimum length of one
   *   character. Not meeting this requirement throws an error.
   *
   * @return ExporterOutputConfigDefinitionPathInterface
   *   This instance, to make this method chainable.
   *
   * @throws \Drupal\static_suite\StaticSuiteUserException
   */
  public function setFilename(string $filename): ExporterOutputConfigDefinitionPathInterface;

  /**
   * Get export extension.
   *
   * @return string|null
   *   The export extension.
   */
  public function getExtension(): ?string;

  /**
   * Set export extension.
   *
   * @param string|null $extension
   *   Export extension. Optional.
   *
   * @return ExporterOutputConfigDefinitionPathInterface
   *   This instance, to make this method chainable.
   */
  public function setExtension(string $extension = NULL): ExporterOutputConfigDefinitionPathInterface;

}
