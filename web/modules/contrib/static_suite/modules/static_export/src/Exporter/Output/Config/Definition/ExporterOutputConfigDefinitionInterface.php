<?php

namespace Drupal\static_export\Exporter\Output\Config\Definition;

use Drupal\Core\Language\LanguageInterface;
use Drupal\static_export\Exporter\Output\Config\Definition\Path\ExporterOutputConfigDefinitionPathInterface;

/**
 * An interface to define the output configuration of a exporter.
 */
interface ExporterOutputConfigDefinitionInterface {

  /**
   * Get the path object to use in a configuration definition.
   *
   * @return \Drupal\static_export\Exporter\Output\Config\Definition\Path\ExporterOutputConfigDefinitionPathInterface
   *   The path object.
   */
  public function getPath(): ExporterOutputConfigDefinitionPathInterface;

  /**
   * Get export language.
   *
   * @return \Drupal\Core\Language\LanguageInterface
   *   The export language.
   */
  public function getLanguage(): LanguageInterface;

  /**
   * Set export language.
   *
   * Do not use the current language unless export operation is done inside a
   * language context. Otherwise, data would be exported to different paths
   * depending on the language of the UI that triggered the operation, which is
   * an error.
   *
   * @param \Drupal\Core\Language\LanguageInterface $language
   *   Export language.
   *
   * @return ExporterOutputConfigDefinitionInterface
   *   This instance, to make this method chainable.
   */
  public function setLanguage(LanguageInterface $language): ExporterOutputConfigDefinitionInterface;

  /**
   * Get export format.
   *
   * If not defined, it uses the extension from the path. If extension is not
   * defined, it will return null. This is a valid behavior, because format is
   * only used when data formatting happens, and that is an optional step
   * (exporters can opt out of formatting using
   * ExporterPluginInterface::OVERRIDE_FORMAT)
   *
   * @return string|null
   *   The export format or null.
   */
  public function getFormat(): ?string;

  /**
   * Set export format.
   *
   * @param string|null $format
   *   Export format. Optional, it can be null or an empty string.
   *
   * @return ExporterOutputConfigDefinitionInterface
   *   This instance, to make this method chainable.
   */
  public function setFormat(string $format): ExporterOutputConfigDefinitionInterface;

}
