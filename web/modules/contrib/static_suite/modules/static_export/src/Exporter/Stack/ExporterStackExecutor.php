<?php

namespace Drupal\static_export\Exporter\Stack;

use Drupal\static_export\Exporter\ExporterPluginInterface;
use Drupal\static_export\File\FileCollectionGroup;
use Drupal\static_export\Messenger\MessengerInterface;
use Drupal\static_suite\StaticSuiteUserException;
use Drupal\static_suite\Utility\StaticSuiteUtilsInterface;
use Throwable;

/**
 * An executor for a stack of exporters.
 */
class ExporterStackExecutor implements ExporterStackExecutorInterface {

  /**
   * Static Suite utils.
   *
   * @var \Drupal\static_suite\Utility\StaticSuiteUtilsInterface
   */
  protected StaticSuiteUtilsInterface $staticSuiteUtils;

  /**
   * The messenger service.
   *
   * @var \Drupal\static_export\Messenger\MessengerInterface
   */
  protected MessengerInterface $messenger;


  /**
   * The exporter stack.
   *
   * @var array[]
   */
  protected array $stack = [];

  /**
   * Constructs a new ExporterStackExecutor instance.
   *
   * @param \Drupal\static_suite\Utility\StaticSuiteUtilsInterface $staticSuiteUtils
   *   Static Suite utils.
   * @param \Drupal\static_export\Messenger\MessengerInterface $messenger
   *   The messenger service.
   */
  public function __construct(StaticSuiteUtilsInterface $staticSuiteUtils, MessengerInterface $messenger) {
    $this->messenger = $messenger;
    $this->staticSuiteUtils = $staticSuiteUtils;

    if ($this->staticSuiteUtils->isRunningOnCli()) {
      drupal_register_shutdown_function([$this, 'executeCli']);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function add(string $key, ExporterPluginInterface $exporter, array $options): void {
    $this->stack[$key] = [
      'exporter' => $exporter,
      'options' => $options,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function reset(): void {
    $this->stack = [];
  }

  /**
   * {@inheritdoc}
   */
  public function execute(): FileCollectionGroup {
    $fileCollectionGroup = new FileCollectionGroup();

    try {
      $fileCollectionGroup = $this->processStack();
    }
    catch (StaticSuiteUserException $e) {
      $this->messenger->addError($e->getMessage());
    }
    catch (Throwable $e) {
      $this->messenger->addException($e);
    }
    finally {
      $this->reset();
    }

    if (!$fileCollectionGroup->isEmpty()) {
      $this->messenger->addFileCollectionGroup($fileCollectionGroup);
    }

    return $fileCollectionGroup;
  }

  /**
   * {@inheritdoc}
   */
  public function executeCli(): FileCollectionGroup {
    try {
      $fileCollectionGroup = $this->processStack();
    }
    finally {
      $this->reset();
    }

    return $fileCollectionGroup;
  }

  /**
   * Executes all exporter operations in the stack.
   *
   * @return \Drupal\static_export\File\FileCollectionGroup
   *   A file collection group with the result of all export operations.
   *
   * @throws \Drupal\static_suite\StaticSuiteException
   * @throws \Drupal\static_suite\StaticSuiteUserException
   */
  protected function processStack(): FileCollectionGroup {
    $fileCollectionGroup = new FileCollectionGroup();

    $stackSize = count($this->stack);
    foreach (array_values($this->stack) as $delta => $item) {
      /** @var \Drupal\static_export\Exporter\ExporterPluginInterface $exporter */
      $exporter = $item['exporter'];

      // If there is more than one item in the stack, set it.
      if ($stackSize > 1) {
        $exporter->setStacked(TRUE);
        $exporter->setStackFileCollectionGroup($fileCollectionGroup);
        if ($delta === 0) {
          $exporter->setIsFirstStackItem(TRUE);
        }
        elseif ($delta === $stackSize - 1) {
          $exporter->setIsLastStackItem(TRUE);
        }
      }

      $options = $item['options'];
      if (isset($options['request-build'])) {
        $exporter->setMustRequestBuild($options['request-build']);
      }
      if (isset($options['request-deploy'])) {
        $exporter->setMustRequestDeploy($options['request-deploy']);
      }
      if (isset($options['operation']['id']) && $options['operation']['id'] === ExporterPluginInterface::OPERATION_DELETE) {
        $fileCollectionGroup = $exporter->delete($options['operation']['args'], $options['standalone'] ?? FALSE, $options['log-to-file'] ?? TRUE, $options['lock'] ?? TRUE);
      }
      else {
        $fileCollectionGroup = $exporter->write($options['operation']['args'], $options['standalone'] ?? FALSE, $options['log-to-file'] ?? TRUE, $options['lock'] ?? TRUE);
      }
    }

    return $fileCollectionGroup;
  }

}
