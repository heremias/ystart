<?php

namespace Drupal\static_export\Exporter\Output\Config;

use Drupal\static_export\Exporter\Output\Config\Definition\ExporterOutputConfigDefinitionInterface;
use Drupal\static_export\Exporter\Output\Uri\UriInterface;

/**
 * An interface to define the output configuration of a exporter.
 */
interface ExporterOutputConfigInterface {

  /**
   * Obtain the representation of this configuration as an URI object.
   *
   * This is where the structure of the output of exporters must be defined.
   *
   * @return \Drupal\static_export\Exporter\Output\Uri\UriInterface
   *   The URI representing this configuration.
   */
  public function uri(): UriInterface;

  /**
   * Get export base directory.
   *
   * @return string
   *   The export base directory.
   */
  public function getBaseDir(): string;

  /**
   * Set export base directory.
   *
   * @param string|null $baseDir
   *   The export base directory. Use NULL to remove it from the resulting uri.
   *
   * @return ExporterOutputConfigInterface
   *   This instance, to make this method chainable.
   */
  public function setBaseDir(string $baseDir = NULL): ExporterOutputConfigInterface;

  /**
   * Get the definition used in this configuration.
   *
   * @return \Drupal\static_export\Exporter\Output\Config\Definition\ExporterOutputConfigDefinitionInterface
   *   The configuration definition.
   */
  public function getDefinition(): ExporterOutputConfigDefinitionInterface;

}
