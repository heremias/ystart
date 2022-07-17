<?php

namespace Drupal\static_export\Exporter\Output\Config\Definition;

use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\static_export\Exporter\Output\Config\Definition\Path\ExporterOutputConfigDefinitionPathInterface;

/**
 * Definition of the output of a exporter.
 *
 * This class is meant to be instantiated using
 * ExporterOutputConfigFactoryInterface::create() method and shouldn't be
 * manually instantiated.
 */
class ExporterOutputConfigDefinition implements ExporterOutputConfigDefinitionInterface {

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The path object to use in a configuration definition.
   *
   * @var \Drupal\static_export\Exporter\Output\Config\Definition\Path\ExporterOutputConfigDefinitionPathInterface
   */
  protected $path;

  /**
   * Export language.
   *
   * @var \Drupal\Core\Language\LanguageInterface
   */
  protected $language;

  /**
   * Export format.
   *
   * @var string
   */
  protected $format;

  /**
   * Exporter output config definition constructor.
   *
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager.
   * @param \Drupal\static_export\Exporter\Output\Config\Definition\Path\ExporterOutputConfigDefinitionPathInterface $path
   *   The path object to use in a configuration definition.
   * @param \Drupal\Core\Language\LanguageInterface|null $language
   *   Optional export language.
   * @param string|null $format
   *   Optional export format.
   */
  public function __construct(LanguageManagerInterface $languageManager, ExporterOutputConfigDefinitionPathInterface $path, LanguageInterface $language = NULL, $format = NULL) {
    $this->languageManager = $languageManager;
    $this->path = $path;
    $this->language = $language;
    $this->format = $format;
  }

  /**
   * {@inheritdoc}
   */
  public function getPath(): ExporterOutputConfigDefinitionPathInterface {
    return $this->path;
  }

  /**
   * {@inheritdoc}
   */
  public function getLanguage(): LanguageInterface {
    return $this->language ?: $this->languageManager->getLanguage(LanguageInterface::LANGCODE_NOT_APPLICABLE);
  }

  /**
   * {@inheritdoc}
   */
  public function setLanguage(LanguageInterface $language): ExporterOutputConfigDefinitionInterface {
    $this->language = $language;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormat(): ?string {
    return $this->format ?: $this->getPath()->getExtension();
  }

  /**
   * {@inheritdoc}
   */
  public function setFormat(string $format): ExporterOutputConfigDefinitionInterface {
    $this->format = $format;
    return $this;
  }

}
