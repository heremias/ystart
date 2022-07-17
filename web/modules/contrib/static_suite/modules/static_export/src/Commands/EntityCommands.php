<?php

namespace Drupal\static_export\Commands;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\StreamWrapper\StreamWrapperInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Drupal\Core\Url;
use Drupal\static_export\Exporter\Output\Config\ExporterOutputConfigFactory;
use Drupal\static_export\Exporter\Type\Entity\EntityExporterPluginManagerInterface;
use Drupal\static_export\File\FileCollectionFormatter;
use Drupal\static_suite\Entity\EntityUtils;
use Drupal\static_suite\StaticSuiteUserException;
use Drush\Commands\DrushCommands;

/**
 * A Drush command file to export data.
 */
class EntityCommands extends DrushCommands {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The entity type bundle info service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity type bundle info service.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfo;

  /**
   * The stream wrapper manager.
   *
   * @var \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface
   */
  protected $streamWrapperManager;

  /**
   * Entity Exporter Manager.
   *
   * @var \Drupal\static_export\Exporter\Type\Entity\EntityExporterPluginManagerInterface
   */
  protected $entityExporterPluginManager;

  /**
   * The Entity Exporter output config factory.
   *
   * @var \Drupal\static_export\Exporter\Output\Config\ExporterOutputConfigFactory
   */
  protected $entityExporterOutputConfigFactory;

  /**
   * Entity Utils.
   *
   * @var \Drupal\static_suite\Entity\EntityUtils
   */
  protected $entityUtils;

  /**
   * Entity Utils.
   *
   * @var \Drupal\static_export\File\FileCollectionFormatter
   */
  protected $fileCollectionFormatter;

  /**
   * Drupal file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * StaticExportCommands constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entityTypeBundleInfo
   *   The entity type bundle info service.
   * @param \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface $streamWrapperManager
   *   The stream wrapper manager.
   * @param \Drupal\static_export\Exporter\Type\Entity\EntityExporterPluginManagerInterface $entityExporterPluginManager
   *   The Entity Exporter Manager.
   * @param \Drupal\static_export\Exporter\Output\Config\ExporterOutputConfigFactory $entityExporterOutputConfigFactory
   *   The Entity Exporter output config factory.
   * @param \Drupal\static_suite\Entity\EntityUtils $entityUtils
   *   Utils for working with entities.
   * @param \Drupal\static_export\File\FileCollectionFormatter $fileCollectionFormatter
   *   File collection formatter.
   */
  public function __construct(
    ConfigFactoryInterface $configFactory,
    FileSystemInterface $fileSystem,
    LanguageManagerInterface $languageManager,
    EntityTypeManagerInterface $entityTypeManager,
    EntityTypeBundleInfoInterface $entityTypeBundleInfo,
    StreamWrapperManagerInterface $streamWrapperManager,
    EntityExporterPluginManagerInterface $entityExporterPluginManager,
    ExporterOutputConfigFactory $entityExporterOutputConfigFactory,
    EntityUtils $entityUtils,
    FileCollectionFormatter $fileCollectionFormatter
  ) {
    parent::__construct();
    $this->configFactory = $configFactory;
    $this->fileSystem = $fileSystem;
    $this->languageManager = $languageManager;
    $this->entityTypeManager = $entityTypeManager;
    $this->entityTypeBundleInfo = $entityTypeBundleInfo;
    $this->streamWrapperManager = $streamWrapperManager;
    $this->entityExporterPluginManager = $entityExporterPluginManager;
    $this->entityExporterOutputConfigFactory = $entityExporterOutputConfigFactory;
    $this->entityUtils = $entityUtils;
    $this->fileCollectionFormatter = $fileCollectionFormatter;
  }

  /**
   * Exports an entity.
   *
   * @param string $entityTypeId
   *   The entity type id to export, e.g., node, user, etc.
   * @param string $entityId
   *   The ID of the entity to export, based on the previous entityType param.
   * @param array $execOptions
   *   An associative array of options whose values come
   *   from cli, aliases, config, etc.
   *
   * @command static-export:export-entity
   *
   * @option standalone Flag to set standalone mode (default standalone FALSE)
   * @option force-write Whether it should force the writing of the output
   * @usage drush see node 1
   *   Export node 1
   * @usage drush see node 1
   *   Export node 1 in json format to default location
   * @aliases see, static-export-entity
   *
   * @static_export Annotation for drush hooks.
   * @static_export_data_dir_write Annotation for commands that write data.
   */
  public function exportEntity(
    string $entityTypeId,
    string $entityId,
    array $execOptions = [
      'standalone' => FALSE,
      'log-to-file' => TRUE,
      'lock' => TRUE,
      'force-write' => FALSE,
      'request-build' => FALSE,
      'request-deploy' => FALSE,
    ]
  ): void {
    // Stop execution if export operations are disabled.
    if (!$this->configFactory->get('static_export.settings')
      ->get('exportable_entity.enabled')) {
      $configUrl = Url::fromRoute('static_export.exportable_entity.settings', [], ['absolute' => FALSE])
        ->toString();
      $this->logger()
        ->warning(t('Export operations for entities are disabled. Please, enable them at @url', ['@url' => $configUrl]));
      return;
    }

    $timeStart = microtime(TRUE);
    try {
      $entityExporter = $this->entityExporterPluginManager->getDefaultInstance();
      $entityExporter->setIsForceWrite($execOptions['force-write']);
      $entityExporter->setMustRequestBuild($execOptions['request-build']);
      $entityExporter->setMustRequestDeploy($execOptions['request-deploy']);
      $entityExporter->setConsoleOutput($this->output());
      $fileCollectionGroup = $entityExporter->export(
        [
          'entity-type-id' => $entityTypeId,
          'entity-id' => $entityId,
        ],
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
    catch (\Throwable $e) {
      $this->logger()->error($e);
    }
  }

  /**
   * Exports an entity type.
   *
   * @param string $entityTypeId
   *   The entity type to export, e.g., node, user, etc.
   * @param int $rangeStart
   *   Range start.
   * @param int $rangeLength
   *   Range length.
   * @param array $execOptions
   *   An associative array of options whose values come
   *   from cli, aliases, config, etc.
   *
   * @command static-export:export-entity-type
   *
   * @option standalone Flag to set standalone mode (default standalone FALSE)
   *   Even thought this command exports all data from a bundle and it should
   *   be executed in standalone mode, we maintain this option's default
   *   value as FALSE to avoid confusing behavior between similar commands.
   *   Hence, you need to explicitly call this command with "--standalone"
   *   option so it works as expected. Calling it without that option won't
   *   broke anything, but it will take a lot more time to finish and will
   *   export some data more than once.
   * @usage drush static-export:export-entity-type node --bundle=article
   *   Export node article bundle
   *
   * @static_export Annotation for drush hooks.
   * @static_export_data_dir_write Annotation for commands that write data.
   */
  public function exportBundle(
    string $entityTypeId,
    int $rangeStart = 0,
    int $rangeLength = 9999999,
    array $execOptions = [
      'bundle' => NULL,
      'status' => NULL,
      'standalone' => FALSE,
      'log-to-file' => TRUE,
      'lock' => TRUE,
      'force-write' => FALSE,
      'request-build' => FALSE,
      'request-deploy' => FALSE,
    ]
  ) {
    // Stop execution if export operations are disabled.
    if (!$this->configFactory->get('static_export.settings')
      ->get('exportable_entity.enabled')) {
      $configUrl = Url::fromRoute('static_export.exportable_entity.settings', [], ['absolute' => FALSE])
        ->toString();
      $this->logger()
        ->warning(t('Export operations for entities are disabled. Please, enable them at @url', ['@url' => $configUrl]));
      return;
    }

    set_time_limit(0);
    $timeStart = microtime(TRUE);
    try {
      $options = [
        'bundle' => $execOptions['bundle'],
        'status' => $execOptions['status'],
      ];
      if ($rangeLength !== 9999999) {
        $options['range'] = [
          'start' => $rangeStart,
          'length' => $rangeLength,
        ];
      }
      if ($entityTypeId === 'node') {
        $options['sort'] = [
          'field' => 'nid',
          'direction' => 'DESC',
        ];
        $entityIds = $this->entityUtils->getEntityIds(
          $entityTypeId,
          $options
        );
      }
      else {
        $entityIds = $this->entityUtils->getEntityIds(
          $entityTypeId,
          $options
        );
      }
      $total = count($entityIds);
      if ($total == 0) {
        $message = 'No entities found for this entity type "' . $entityTypeId . '"';
        if (!is_null($options['bundle'])) {
          $message .= ' and this bundle "' . $execOptions['bundle'] . '"';
        }
        $this->logger()->error($message);
      }
      else {
        $delta = 1;
        $entityExporter = $this->entityExporterPluginManager->getDefaultInstance();
        $entityExporter->setIsForceWrite($execOptions['force-write']);
        $entityExporter->setMustRequestBuild($execOptions['request-build']);
        $entityExporter->setMustRequestDeploy($execOptions['request-deploy']);
        $entityExporter->setConsoleOutput($this->output());
        foreach ($entityIds as $entityId) {
          $fileCollectionGroup = $entityExporter->export(
            [
              'entity-type-id' => $entityTypeId,
              'entity-id' => $entityId,
            ],
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
      }
      $this->output()
        ->writeln("TOTAL TIME: " . (round(microtime(TRUE) - $timeStart, 3)) . " secs.");
    }
    catch (StaticSuiteUserException $e) {

      $this->logger()->error($e->getMessage());

    }

    catch (\Throwable $e) {
      $this->logger()->error($e);
    }
  }

  /**
   * Check that exported files have their corresponding entity in Drupal.
   *
   * When working with thousands of exported files, there can be some race
   * conditions where an entity is deleted but its exported file is not. This
   * command analyzes the data directory to find any stale file and, optionally,
   * delete them.
   *
   * @param string $entityTypeId
   *   The entity type id to check, e.g., node, user, etc.
   * @param array $execOptions
   *   An associative array of options whose values come
   *   from cli, aliases, config, etc.
   *
   * @command static-export:entity:check-integrity
   *
   * @option bundle   Name of the bundle to check, relative to the
   *                  param $entityTypeId
   * @option langcode Name of the language to check. If not provided, the
   *                  default language is used.
   *
   * @usage drush static-export:entity:check-integrity node --bundle=article
   *        --langcode=en
   *
   * @static_export Annotation for drush hooks.
   *
   * @throws \JsonException
   */
  public function checkExportedEntityIntegrity(
    string $entityTypeId,
    array $execOptions = [
      'bundle' => NULL,
      'langcode' => NULL,
    ]
  ) {

    // Before anything else, check if this command is executable based on
    // current stream wrapper configuration.
    $scheme = $this->configFactory->get('static_export.settings')
      ->get('uri.scheme');
    $streamWrapper = $this->streamWrapperManager->getViaScheme($scheme);
    if (!$streamWrapper) {
      $this->logger()
        ->error('No stream wrapper available for scheme "' . $scheme . '://"');
      return;
    }
    if ($streamWrapper::getType() !== StreamWrapperInterface::LOCAL_NORMAL) {
      $this->logger()
        ->error('The stream wrapper "' . $scheme . '://" is remote and not compatible with this command.');
      return;
    }

    // Check entity type id.
    $availableEntityTypes = $this->entityTypeManager->getDefinitions();
    if (!array_key_exists($entityTypeId, $availableEntityTypes)) {
      $this->logger()
        ->error('The provided entity type is not valid: "' . $entityTypeId . '"');
      return;
    }

    // Check bundle.
    if ($execOptions['bundle']) {
      $availableBundles = $this->entityTypeBundleInfo->getBundleInfo($entityTypeId);
      if (!array_key_exists($execOptions['bundle'], $availableBundles)) {
        $this->logger()
          ->error('The provided bundle is not valid: "' . $execOptions['bundle'] . '"');
        return;
      }
    }

    // Check langcode.
    if ($execOptions['langcode']) {
      $availableLanguages = $this->languageManager->getLanguages(LanguageInterface::STATE_ALL);
      if (!isset($availableLanguages[$execOptions['langcode']])) {
        $this->logger()
          ->error('The provided langcode is not valid: "' . $execOptions['langcode'] . '"');
        return;
      }
    }

    // Define the directory to check.
    $dataDir = $streamWrapper->realpath();
    $languageDir = $execOptions['langcode'] ?? $this->languageManager->getDefaultLanguage()
      ->getId();
    $checkDirPaths = [
      $dataDir,
      $languageDir,
      $this->entityExporterOutputConfigFactory->getDefaultBaseDir(),
      $entityTypeId,
      $execOptions['bundle'],
    ];
    $checkDir = implode('/', $checkDirPaths);

    // Check the directory exists.
    if (!is_dir($checkDir) || !is_readable($checkDir)) {
      $this->logger()
        ->error('Directory to check not found: "' . $checkDir . '"');
      return;
    }

    $pathsToCheck = new \RecursiveIteratorIterator(
      new \RecursiveDirectoryIterator($checkDir)
    );
    $pathsToCheck = iterator_to_array($pathsToCheck);

    $totalPathsToCheck = count($pathsToCheck);
    $this->io()->title("Checking " . $totalPathsToCheck . " files");
    $this->io()->progressStart($totalPathsToCheck);
    // Iterate over all paths in the directory.
    $staleFiles = [];
    foreach ($pathsToCheck as $path) {
      $this->io()->progressAdvance();
      if ($path->isDir()) {
        continue;
      }

      // Check that filename contains a sequence of digits, corresponding to
      // the entity ID.
      preg_match('/^(\d*)\.\w+$/', $path->getFilename(), $matches);
      $entityId = empty($matches[1]) ? NULL : $matches[1];

      // If so, it is an entity, so try to load it.
      if ($entityId) {
        $entity = $this->entityUtils->loadEntity($entityTypeId, $entityId);
        // If entity is not present, we have found a stale file!
        if (!$entity) {
          $staleFiles[] = $path;
        }
      }
    }
    $this->io()->progressFinish();

    // Show findings in a table.
    if (count($staleFiles)) {
      $rows = [];
      foreach ($staleFiles as $staleFile) {
        // Log this finding, adding data form JSON file if available.
        $row = [];
        $row['pathname'] = $staleFile->getPathname();
        if ($staleFile->getExtension() === 'json') {
          $json = json_decode(file_get_contents($staleFile->getPathname()), TRUE, 512, JSON_THROW_ON_ERROR);
          $row['url'] = $json['data']['content']['url']['path'] ?? '--';
          $row['title'] = $json['data']['content']['title'] ?? '--';
        }
        $rows[] = $row;
      }
      // Display table.
      $totalStaleFiles = count($staleFiles);
      $this->io()->title('Found ' . $totalStaleFiles . ' stale files');
      $this->io()->table(['Pathname', 'URL', 'Title'], $rows);

      // Ask for permission to delete stale files.
      if ($this->io()
        ->confirm('Do you want to delete the above ' . $totalStaleFiles . ' stale files?', FALSE)) {
        foreach ($staleFiles as $staleFile) {
          $this->fileSystem->unlink($staleFile->getPathname());
          $this->output()
            ->writeln($staleFile->getPathname() . ' successfully deleted');
        }
      }
      else {
        $this->io()->note('Aborted.');
      }
    }
    else {
      $this->io()->success("No stale files found.");
    }
  }

}
