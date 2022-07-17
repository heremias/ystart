<?php

namespace Drupal\static_export\Commands;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Url;
use Drupal\static_export\Exporter\Type\Config\ConfigExporterPluginManagerInterface;
use Drupal\static_export\File\FileCollectionFormatter;
use Drupal\static_suite\StaticSuiteUserException;
use Drush\Commands\DrushCommands;
use Throwable;

/**
 * A Drush command file to export drupal config data.
 */
class ConfigCommands extends DrushCommands {

  /**
   * Drupal's config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Config exporter manager.
   *
   * @var \Drupal\static_export\Exporter\Type\Config\ConfigExporterPluginManagerInterface
   */
  protected $configExporterManager;

  /**
   * File collection formatter.
   *
   * @var \Drupal\static_export\File\FileCollectionFormatter
   */
  protected $fileCollectionFormatter;

  /**
   * StaticExportCommands constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Drupal's config factory.
   * @param \Drupal\static_export\Exporter\Type\Config\ConfigExporterPluginManagerInterface $configExporterManager
   *   Config exporter manager.
   * @param \Drupal\static_export\File\FileCollectionFormatter $file_collection_formatter
   *   File collection formatter.
   */
  public function __construct(ConfigFactoryInterface $config_factory, ConfigExporterPluginManagerInterface $configExporterManager, FileCollectionFormatter $file_collection_formatter) {
    parent::__construct();
    $this->configFactory = $config_factory;
    $this->configExporterManager = $configExporterManager;
    $this->fileCollectionFormatter = $file_collection_formatter;
  }

  /**
   * Exports Drupal configuration objects.
   *
   * @param string|null $configName
   *   The name of the Drupal config to export. Optional.
   * @param array $execOptions
   *   An associative array of options whose values come
   *   from cli, aliases, config, etc.
   *
   * @command static-export:export-config
   *
   * @option standalone Flag to set standalone mode
   * @usage drush static-export:export-config
   *   Exports drupal config
   *
   * @static_export Annotation for drush hooks.
   * @static_export_data_dir_write Annotation for commands that write data.
   */
  public function exportConfig(
    string $configName = NULL,
    array $execOptions = [
      'standalone' => FALSE,
      'log-to-file' => TRUE,
      'lock' => TRUE,
      'build' => FALSE,
    ]
  ): void {
    // Stop execution if export operations are disabled.
    if (!$this->configFactory->get('static_export.settings')
      ->get('exportable_config.enabled')) {
      $configUrl = Url::fromRoute('static_export.exportable_config.settings', [], ['absolute' => FALSE])
        ->toString();
      $this->logger()
        ->warning(t('Export operations for configuration objects are disabled. Please, enable them at @url', ['@url' => $configUrl]));
      return;
    }

    $timeStart = microtime(TRUE);
    try {
      $configExporter = $this->configExporterManager->getDefaultInstance();
      if (!empty($configName)) {
        $exportableConfigNames[] = $configName;
      }
      else {
        $exportableConfigNames = $this->configFactory->get('static_export.settings')
          ->get('exportable_config.objects_to_export');
      }
      $delta = 1;
      $total = count($exportableConfigNames);
      $configExporter->setMustRequestBuild($execOptions['build']);
      $configExporter->setConsoleOutput($this->output());
      foreach ($exportableConfigNames as $exportableConfigName) {
        $fileCollectionGroup = $configExporter->export(
          ['name' => $exportableConfigName],
          $execOptions['standalone'],
          $execOptions['log-to-file'],
          $execOptions['lock']
        );
        foreach ($fileCollectionGroup->getFileCollections() as $fileCollection) {
          $this->fileCollectionFormatter->setFileCollection($fileCollection);
          $this->output()
            ->writeln($this->fileCollectionFormatter->getTextLines($delta, $total));
        }
        $delta++;
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
