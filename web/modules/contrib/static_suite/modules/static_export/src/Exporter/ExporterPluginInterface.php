<?php

namespace Drupal\static_export\Exporter;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\static_export\Exporter\Output\Uri\UriInterface;
use Drupal\static_export\File\FileCollectionGroup;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Defines an interface for exporting data to static files.
 */
interface ExporterPluginInterface extends PluginInspectionInterface, ContainerFactoryPluginInterface {

  public const OPERATION_WRITE = 'static_export:write';

  public const OPERATION_DELETE = 'static_export:delete';

  /**
   * Name to be used for semaphores when reading or writing data to data dir.
   */
  public const DATA_DIR_LOCK_NAME = 'static_export_data_dir';

  /**
   * Special key to bypass formatting data returned by exporters.
   *
   * @todo - Find a better way to implement this.
   */
  public const OVERRIDE_FORMAT = '___EXPORTER_OVERRIDE_FORMAT___';

  /**
   * Separator for variant data files saved to storage.
   *
   * Refers to the separator that is used by variant data files when they are
   * saved. If a "master" data file name is "content.12345.json", and its
   * variant keys are "card" and "search", its resulting variant file names are:
   *   - content.12345--card.json
   *   - content.12345--search.json.
   */
  public const VARIANT_SEPARATOR = '--';

  /**
   * Preprocess options array.
   *
   * Useful when you need to load some kind of entity based on the values of
   * $options, and/or to reorganize those $options.
   *
   * Not all exporters may need this, so it should be defined in a base
   * abstract class
   *
   * @param array $options
   *   Options for export operation.
   *
   * @return array
   *   Processed $options.
   */
  public function preProcessOptions(array $options): array;

  /**
   * Checks incoming params.
   *
   * Every exporter should check its own params and throw a exception
   * on any problem.
   *
   * @param array $options
   *   Options for this operation.
   *
   * @return bool
   *   True if params are ok.
   *
   * @throws \Drupal\static_suite\StaticSuiteUserException
   * @see \Drupal\static_export\Exporter\ExporterPluginInterface::setOptions()
   */
  public function checkParams(array $options): bool;

  /**
   * Set options for this operation.
   *
   * A common scenario that should be implemented is as follows:
   *
   * First, it preprocess options to fix/decorate some of them if needed.
   * Then, it check for options validity.
   * Last, it assigns options to the exporter instance.
   *
   * Internally, it should use the following methods in this order:
   * 1) ExporterInterface::preProcessOptions()
   * 2) ExporterInterface::checkParams()
   *
   * @param array $options
   *   An array of options for this operation.
   *
   * @return ExporterPluginInterface
   *   The exporter's instance.
   *
   * @throws \Drupal\static_suite\StaticSuiteUserException
   */
  public function setOptions(array $options): ExporterPluginInterface;

  /**
   * Get options array.
   *
   * @return array
   *   Options for this operation.
   */
  public function getOptions(): array;

  /**
   * Get operation (write / delete)
   *
   * @return string
   *   Operation mode.
   * @todo - make this just an option
   */
  public function getOperation(): string;

  /**
   * Tell whether exporter must request a build.
   *
   * @return bool
   *   Build flag.
   */
  public function mustRequestBuild(): bool;

  /**
   * Set whether exporter must request a build.
   *
   * @param bool $flag
   *   Build flag.
   */
  public function setMustRequestBuild(bool $flag);

  /**
   * Tell whether exporter must request a deployment.
   *
   * @return bool
   *   Deploy flag.
   */
  public function mustRequestDeploy(): bool;

  /**
   * Set whether exporter must request a deployment.
   *
   * @param bool $flag
   *   Deploy flag.
   */
  public function setMustRequestDeploy(bool $flag);

  /**
   * Set the console output when running on CLI.
   *
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *   The console output object.
   */
  public function setConsoleOutput(OutputInterface $output): void;

  /**
   * Get the console output when running on CLI.
   *
   * @return \Symfony\Component\Console\Output\OutputInterface|null
   *   The console output object.
   */
  public function getConsoleOutput(): ?OutputInterface;

  /**
   * Set whether this operation is stacked along other export operations.
   *
   * @param bool $stacked
   *   A flag to set this value true or false.
   *
   * @return ExporterPluginInterface
   *   The exporter instance
   */
  public function setStacked(bool $stacked): ExporterPluginInterface;

  /**
   * Get whether this operation is stacked along other export operations.
   *
   * @return bool
   *   True or false.
   */
  public function isStacked(): bool;

  /**
   * Set whether this is the first item of a stack.
   *
   * @param bool $isFirstStackItem
   *   A flag to set this value true or false.
   *
   * @return ExporterPluginInterface
   *   The exporter instance
   */
  public function setIsFirstStackItem(bool $isFirstStackItem): ExporterPluginInterface;

  /**
   * Tell whether this is the first item of a stack.
   *
   * @return bool
   *   True or false.
   */
  public function isFirstStackItem(): bool;

  /**
   * Set whether this is the last item of a stack.
   *
   * @param bool $isLastStackItem
   *   A flag to set this value true or false.
   *
   * @return ExporterPluginInterface
   *   The exporter instance
   */
  public function setIsLastStackItem(bool $isLastStackItem): ExporterPluginInterface;

  /**
   * Tell whether this is the last item of a stack.
   *
   * @return bool
   *   True or false.
   */
  public function isLastStackItem(): bool;

  /**
   * Exports a single item.
   *
   * @param array $options
   *   Options for export operation.
   * @param bool $isStandalone
   *   Flag for standalone mode.
   * @param bool $logToFile
   *   Whether it should log data to a logfile.
   * @param bool $lock
   *   Whether it should enable locking for disk operations.
   *
   * @return \Drupal\static_export\File\FileCollectionGroup
   *   A FileCollectionGroup of FileCollections with FileItems.
   *
   * @throws \Drupal\static_suite\StaticSuiteException
   * @throws \Drupal\static_suite\StaticSuiteUserException *
   */
  public function write(array $options = [], bool $isStandalone = FALSE, bool $logToFile = TRUE, bool $lock = TRUE);

  /**
   * Exports a single item.
   *
   * @param array $options
   *   Options for export operation.
   * @param bool $isStandalone
   *   Flag for standalone mode.
   * @param bool $logToFile
   *   Whether it should log data to a logfile.
   * @param bool $lock
   *   Whether it should enable locking for disk operations.
   *
   * @return \Drupal\static_export\File\FileCollectionGroup
   *   A FileCollectionGroup of FileCollections with FileItems.
   *
   * @throws \Drupal\static_suite\StaticSuiteException
   * @throws \Drupal\static_suite\StaticSuiteUserException *
   * @todo rename to write
   */
  public function export(array $options = [], bool $isStandalone = FALSE, bool $logToFile = TRUE, bool $lock = TRUE);

  /**
   * Deletes a single item.
   *
   * @param array $options
   *   Options for delete operation.
   * @param bool $isStandalone
   *   Flag for standalone mode.
   * @param bool $logToFile
   *   Whether it should log data to a logfile.
   * @param bool $lock
   *   Whether it should enable locking for disk operations.
   *
   * @return \Drupal\static_export\File\FileCollectionGroup
   *   A FileCollectionGroup of FileCollection with FileItems.
   *
   * @throws \Drupal\static_suite\StaticSuiteException
   * @throws \Drupal\static_suite\StaticSuiteUserException
   */
  public function delete(array $options = [], bool $isStandalone = FALSE, bool $logToFile = TRUE, bool $lock = TRUE);

  /**
   * Logs a message.
   *
   * @param string $message
   *   A message to be logged.
   */
  public function logMessage(string $message): void;

  /**
   * Resets the exporter.
   */
  public function reset();

  /**
   * Make this exporter slave of another one.
   *
   * Set and copy some values to ensure proper execution on sub exports.
   *
   * @param ExporterPluginInterface $masterExporter
   *   The master exporter instance.
   *
   * @return ExporterPluginInterface
   *   Return $this so this method can be chainable
   */
  public function makeSlaveOf(ExporterPluginInterface $masterExporter): ExporterPluginInterface;

  /**
   * Tells whether an exporter considers an item exportable.
   *
   * This method is used in two places:
   *  1) Internally, inside a exporter, to avoid exporting something not
   *     allowed. An exception is thrown if that happens.
   *  2) Outside exporters, as an early check to avoid passing wrong parameters
   *     to them. This way, no exception is thrown.
   *
   * @param array $options
   *   An array of options, just like the one received by export() method.
   *
   * @return bool
   *   True if it's exportable
   */
  public function isExportable(array $options): bool;

  /**
   * Get URI where data is stored, in a format usable by a stream wrapper.
   *
   * It can be obtained without actually running the exporter.
   *
   * @return \Drupal\static_export\Exporter\Output\Uri\UriInterface|null
   *   URI where data is stored
   */
  public function getUri(): ?UriInterface;

  /**
   * Get variant keys of a exporter.
   *
   * Each exporter could implement a different strategy to determine its
   * variant keys, if any. For example, EntityExporter uses its own
   * StaticResolver plugins, while other exporters, like LocaleExporter,
   * could export a fixed variant with a subset of locales.
   *
   * This method dispatches events so variant keys can be customized.
   *
   * @return array
   *   Array of strings
   */
  public function getVariantKeys(): array;

  /**
   * Get translation languages of a exporter.
   *
   * Each exporter should implement a different strategy to determine its
   * translation languages, if any. For example, EntityExporter uses
   * TranslatableInterface::getTranslationLanguages() for translatable contents,
   * while ConfigExporter uses LocaleConfigManager::hasTranslation()
   *
   * @return array
   *   Array of strings
   */
  public function getTranslationLanguages(): array;

  /**
   * Get the resulting FileCollectionGroup.
   *
   * @return \Drupal\static_export\File\FileCollectionGroup
   *   The resulting FileCollectionGroup.
   */
  public function getResultingFileCollectionGroup(): FileCollectionGroup;

  /**
   * Set the FileCollectionGroup from the exporters stack.
   *
   * @param \Drupal\static_export\File\FileCollectionGroup $stackFileCollectionGroup
   *   The FileCollectionGroup from the exporters stack.
   */
  public function setStackFileCollectionGroup(FileCollectionGroup $stackFileCollectionGroup): ExporterPluginInterface;

}
