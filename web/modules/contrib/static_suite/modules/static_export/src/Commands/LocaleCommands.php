<?php

namespace Drupal\static_export\Commands;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Url;
use Drupal\static_export\Exporter\Type\Locale\LocaleExporterPluginManagerInterface;
use Drupal\static_export\File\FileCollectionFormatter;
use Drupal\static_suite\StaticSuiteUserException;
use Drush\Commands\DrushCommands;
use Throwable;

/**
 * A Drush command file to export locale data.
 */
class LocaleCommands extends DrushCommands {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * Locale exporter manager.
   *
   * @var \Drupal\static_export\Exporter\Type\Locale\LocaleExporterPluginManagerInterface
   */
  protected $localeExporterManager;

  /**
   * File collection formatter.
   *
   * @var \Drupal\static_export\File\FileCollectionFormatter
   */
  protected $fileCollectionFormatter;

  /**
   * StaticExportCommands constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   Language manager.
   * @param \Drupal\static_export\Exporter\Type\Locale\LocaleExporterPluginManagerInterface $localeExporterManager
   *   Locale exporter manager.
   * @param \Drupal\static_export\File\FileCollectionFormatter $file_collection_formatter
   *   File collection formatter.
   */
  public function __construct(ConfigFactoryInterface $configFactory, LanguageManagerInterface $language_manager, LocaleExporterPluginManagerInterface $localeExporterManager, FileCollectionFormatter $file_collection_formatter) {
    parent::__construct();
    $this->configFactory = $configFactory;
    $this->languageManager = $language_manager;
    $this->localeExporterManager = $localeExporterManager;
    $this->fileCollectionFormatter = $file_collection_formatter;
  }

  /**
   * Exports localized strings.
   *
   * @param string|null $langcode
   *   The language id to export. Optional. If none provided, all languages
   *   are exported.
   * @param array $execOptions
   *   An associative array of options whose values come
   *   from cli, aliases, config, etc.
   *
   * @command static-export:export-locale
   *
   * @option standalone Flag to set standalone mode
   * @usage drush static-export:export-locale pt-br
   *   Exports locales.
   *
   * @static_export Annotation for drush hooks.
   * @static_export_data_dir_write Annotation for commands that write data.
   */
  public function exportLocale(
    string $langcode = NULL,
    array $execOptions = [
      'standalone' => FALSE,
      'log-to-file' => TRUE,
      'lock' => TRUE,
      'build' => FALSE,
      'force-write' => FALSE,
    ]
  ): void {
    // Stop execution if export operations are disabled.
    if (!$this->configFactory->get('static_export.settings')
      ->get('exportable_locale.enabled')) {
      $configUrl = Url::fromRoute('static_export.exportable_locale.settings', [], ['absolute' => FALSE])
        ->toString();
      $this->logger()
        ->warning(t('Export operations for locales are disabled. Please, enable them at @url', ['@url' => $configUrl]));
      return;
    }

    $timeStart = microtime(TRUE);

    $langCodesToExport = [];
    if ($langcode) {
      $langCodesToExport[] = $langcode;
    }
    else {
      foreach ($this->languageManager->getLanguages() as $language) {
        $langCodesToExport[] = $language->getId();
      }
    }

    try {
      $total = count($langCodesToExport);
      $localeExporter = $this->localeExporterManager->getDefaultInstance();
      foreach ($langCodesToExport as $delta => $langCode) {
        $localeExporter->setIsForceWrite($execOptions['force-write']);
        $localeExporter->setMustRequestBuild($execOptions['build']);
        $localeExporter->setConsoleOutput($this->output());
        $fileCollectionGroup = $localeExporter->export(
          ['langcode' => $langCode],
          $execOptions['standalone'],
          $execOptions['log-to-file'],
          $execOptions['lock']
        );
        foreach ($fileCollectionGroup->getFileCollections() as $fileCollection) {
          $this->fileCollectionFormatter->setFileCollection($fileCollection);
          $this->output()
            ->writeln($this->fileCollectionFormatter->getTextLines($delta + 1, $total));
        }
      }
      $this->output()
        ->writeln("TOTAL TIME: " . (round(microtime(TRUE) - $timeStart, 3)) . " secs.");
    }
    catch (StaticSuiteUserException | PluginException $e) {
      $this->logger()->error($e->getMessage());
    }
    catch (Throwable $e) {
      $this->logger()->error($e);
    }
  }

}
