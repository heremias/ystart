<?php

namespace Drupal\static_export\Exporter\Stack;

use Drupal\static_export\Exporter\ExporterPluginInterface;
use Drupal\static_export\File\FileCollectionGroup;

/**
 * An interface for export stack executors.
 */
interface ExporterStackExecutorInterface {

  /**
   * Add an item to the stack of export processes to be executed.
   *
   * @param string $key
   *   A key to identify the export operation (e.g.- 'node:12',
   *   'custom-exporter-id', etc.) Its main use is to avoid executing the same
   *   operation twice.
   * @param \Drupal\static_export\Exporter\ExporterPluginInterface $exporter
   *   An instance of an exporter that will be executed later.
   * @param array $options
   *   An array of keyed options to define how the exporter will be executed:
   *   - operation:
   *      - id: one of ExporterPluginInterface::OPERATION_WRITE or
   *   ExporterPluginInterface::OPERATION_DELETE.
   *      - args: an array or keyed arguments to be passed to the operation
   *   method.
   *   - standalone: a boolean for standalone mode.
   *   - log-to-file: a boolean to tell whether it should log data to a
   *   logfile.
   *   - lock: a boolean to tell whether it should enable locking for export
   *   operations.
   *   - request-build: a boolean to request a build after finishing the export
   *   operation.
   *   - request-deploy: a boolean to request a deployment after finishing the
   *   build operation.
   */
  public function add(string $key, ExporterPluginInterface $exporter, array $options): void;

  /**
   * Reset the stack and remove all items.
   */
  public function reset(): void;

  /**
   * Execute all exporters in the stack, set during a Drupal request (via web).
   *
   * @return \Drupal\static_export\File\FileCollectionGroup
   *   A file collection group with the result of all export operations.
   */
  public function execute(): FileCollectionGroup;

  /**
   * Execute all exporters in the stack, set during a CLI process (drush).
   *
   * @return \Drupal\static_export\File\FileCollectionGroup
   *   A file collection group with the result of all export operations.
   *
   * @throws \Drupal\static_suite\StaticSuiteException
   * @throws \Drupal\static_suite\StaticSuiteUserException
   */
  public function executeCli(): FileCollectionGroup;

}
