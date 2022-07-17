<?php

namespace Drupal\static_suite\Plugin;

use Drupal\Component\Plugin\ConfigurableInterface;

/**
 * Base class for plugins that execute a configurable asynchronous task.
 *
 * The main difference with Drupal\static_suite\Plugin\AsyncTaskPluginBase is
 * that a configurable task can have several parameters externally configured,
 * and can  be configured to be synchronously run even when run from a
 * web-server.
 */
abstract class ConfigurableAsyncTaskPluginBase extends AsyncTaskPluginBase implements ConfigurableInterface {

  /**
   * {@inheritdoc}
   */
  public function getConfiguration() {
    return $this->configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    $this->configuration = $configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'sync' => FALSE,
      'cwd' => DRUPAL_ROOT,
      'env' => NULL,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getCwd(): ?string {
    return $this->configuration['cwd'] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getEnv(): ?array {
    return $this->configuration['env'] ?? NULL;
  }

  /**
   * {@inheritdoc}
   *
   * Take configuration into account to decide whether it should fork a
   * new  process, but always keeping in mind that it shouldn't fork on a CLI.
   */
  public function isAsync(): bool {
    return isset($this->configuration['sync']) && $this->configuration['sync'] === FALSE && PHP_SAPI !== 'cli';
  }

}
