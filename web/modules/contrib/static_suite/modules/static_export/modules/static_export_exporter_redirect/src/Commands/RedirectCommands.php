<?php

namespace Drupal\static_export_exporter_redirect\Commands;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\static_export\Exporter\Type\Custom\CustomExporterPluginManagerInterface;
use Drupal\static_export\File\FileCollectionFormatter;
use Drupal\static_suite\StaticSuiteUserException;
use Drush\Commands\DrushCommands;
use Throwable;

/**
 * A Drush command file to export redirect data.
 */
class RedirectCommands extends DrushCommands {

  /**
   * Custom exporter manager.
   *
   * @var \Drupal\static_export\Exporter\Type\Custom\CustomExporterPluginManagerInterface
   */
  protected CustomExporterPluginManagerInterface $customExporterPluginManager;

  /**
   * File collection formatter.
   *
   * @var \Drupal\static_export\File\FileCollectionFormatter
   */
  protected FileCollectionFormatter $fileCollectionFormatter;

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
   * Exports redirects from Redirect contributed module.
   *
   * @param array $execOptions
   *   An associative array of options whose values come
   *   from cli, aliases, config, etc.
   *
   * @command static-export:redirects
   *
   * @static_export Annotation for drush hooks.
   * @static_export_data_dir_write Annotation for commands that write data.
   */
  public function exportRedirect(
    array $execOptions = [
      'standalone' => FALSE,
      'log-to-file' => TRUE,
      'lock' => TRUE,
      'build' => FALSE,
    ]
  ) {
    $timeStart = microtime(TRUE);
    try {
      $redirectExporter = $this->customExporterPluginManager->createInstance('redirect');
      $redirectExporter->setMustRequestBuild($execOptions['build']);
      $fileCollectionGroup = $redirectExporter->export(
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
