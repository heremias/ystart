<?php

namespace Drupal\static_export_exporter_language_metadata\Commands;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\static_export\Exporter\Type\Custom\CustomExporterPluginManagerInterface;
use Drupal\static_export\File\FileCollectionFormatter;
use Drupal\static_suite\StaticSuiteUserException;
use Drush\Commands\DrushCommands;
use Throwable;

/**
 * A Drush command file to export language metadata.
 */
class LanguageMetadataCommands extends DrushCommands {

  /**
   * Custom exporter manager.
   *
   * @var \Drupal\static_export\Exporter\Type\Custom\CustomExporterPluginManagerInterface
   */
  protected $customExporterPluginManager;

  /**
   * File collection formatter.
   *
   * @var \Drupal\static_export\File\FileCollectionFormatter
   */
  protected $fileCollectionFormatter;

  /**
   * Constructor.
   *
   * @param \Drupal\static_export\Exporter\Type\Custom\CustomExporterPluginManagerInterface $customExporterPluginManager
   *   Custom exporter manager.
   * @param \Drupal\static_export\File\FileCollectionFormatter $file_collection_formatter
   *   File collection formatter.
   */
  public function __construct(CustomExporterPluginManagerInterface $customExporterPluginManager, FileCollectionFormatter $file_collection_formatter) {
    parent::__construct();
    $this->customExporterPluginManager = $customExporterPluginManager;
    $this->fileCollectionFormatter = $file_collection_formatter;
  }

  /**
   * Exports Drupal language metadata.
   *
   * @param array $execOptions
   *   An associative array of options whose values come
   *   from cli, aliases, config, etc.
   *
   * @command static-export:language-metadata
   *
   * @static_export Annotation for drush hooks.
   * @static_export_data_dir_write Annotation for commands that write data.
   */
  public function exportLanguageMetadata(
    array $execOptions = [
      'standalone' => FALSE,
      'log-to-file' => TRUE,
      'lock' => TRUE,
      'build' => FALSE,
    ]
  ) {
    $timeStart = microtime(TRUE);
    try {
      $languageConfigExporter = $this->customExporterPluginManager->createInstance('language-metadata');
      $languageConfigExporter->setMustRequestBuild($execOptions['build']);
      $fileCollectionGroup = $languageConfigExporter->export(
        [],
        $execOptions['standalone'],
        $execOptions['log-to-file'],
        $execOptions['lock']
      );
      foreach ($fileCollectionGroup->getFileCollections() as $fileCollection) {
        $this->fileCollectionFormatter->setFileCollection($fileCollection);
        $this->output()
          ->writeln($this->fileCollectionFormatter->getTextLines());
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
