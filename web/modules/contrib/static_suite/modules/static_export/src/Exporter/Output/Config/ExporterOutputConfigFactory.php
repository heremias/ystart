<?php

namespace Drupal\static_export\Exporter\Output\Config;

use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\static_export\Exporter\Output\Config\Definition\ExporterOutputConfigDefinition;
use Drupal\static_export\Exporter\Output\Config\Definition\Path\ExporterOutputConfigDefinitionPath;
use Drupal\static_export\Exporter\Output\Uri\UriFactoryInterface;
use Drupal\static_suite\Security\FilePathSanitizerInterface;

/**
 * A class that creates ExporterOutputConfigInterface objects.
 *
 * This class is in charge of setting where are exported files saved. Each
 * exporter type (entity, config, locale and custom) use a different instance of
 * a ExporterOutputConfigFactory, previously configured to work with that
 * exporter. That configuration is basically the base directory ($baseDir) where
 * files are saved.
 *
 * That $baseDir is a special value, and shouldn't be changed by any developer
 * implementing the interfaces offered by this module. Instead of defining it in
 * an interface, which leads to problems derived by constants not being
 * overridable, we define it in this module's service.yml file, to ensure it's
 * not changed (or at least it's not easy to do it).
 */
class ExporterOutputConfigFactory implements ExporterOutputConfigFactoryInterface {

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The file path sanitizer.
   *
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
   * The default base dir for exported files.
   *
   * @var string
   */
  protected $defaultBaseDir;

  /**
   * ExporterOutputConfigFactory constructor.
   *
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager.
   * @param \Drupal\static_suite\Security\FilePathSanitizerInterface $filePathSanitizer
   *   The file path sanitizer.
   * @param \Drupal\static_export\Exporter\Output\Uri\UriFactoryInterface $uriFactory
   *   The URI factory.
   * @param string $defaultBaseDir
   *   The default base dir for exported files.
   */
  public function __construct(LanguageManagerInterface $languageManager, FilePathSanitizerInterface $filePathSanitizer, UriFactoryInterface $uriFactory, string $defaultBaseDir) {
    $this->languageManager = $languageManager;
    $this->filePathSanitizer = $filePathSanitizer;
    $this->uriFactory = $uriFactory;
    $this->defaultBaseDir = $this->filePathSanitizer->sanitizePath($defaultBaseDir);
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultBaseDir(): string {
    return $this->defaultBaseDir;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\static_suite\StaticSuiteUserException
   */
  public function create(string $dir, string $filename, string $extension = NULL, LanguageInterface $language = NULL, string $format = NULL): ExporterOutputConfigInterface {
    // First, create a path.
    $path = new ExporterOutputConfigDefinitionPath($this->filePathSanitizer, $dir, $filename, $extension);

    // With that path, create a config definition, which is in charge of setting
    // the language and output format.
    $definition = new ExporterOutputConfigDefinition($this->languageManager, $path, $language, $format);

    // Finally, create and return the configuration based on the definition.
    return new ExporterOutputConfig(
      $this->filePathSanitizer,
      $this->uriFactory,
      $this->defaultBaseDir,
      $definition
    );
  }

}
