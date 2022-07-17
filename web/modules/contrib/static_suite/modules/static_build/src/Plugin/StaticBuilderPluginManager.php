<?php

namespace Drupal\static_build\Plugin;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\static_build\Annotation\StaticBuilder;
use Drupal\static_suite\Plugin\CacheablePluginManager;
use Drupal\static_suite\StaticSuiteException;
use Traversable;

/**
 * Provides the Static Builder plugin manager.
 */
class StaticBuilderPluginManager extends CacheablePluginManager implements StaticBuilderPluginManagerInterface {

  use StringTranslationTrait;

  /**
   * Constructs a new StaticBuilderManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   */
  public function __construct(Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct('Plugin/static_build/StaticBuilder', $namespaces, $module_handler, StaticBuilderPluginInterface::class, StaticBuilder::class);

    $this->alterInfo('static_build_static_builder_info');
    $this->setCacheBackend($cache_backend, 'static_build_static_builder_plugins');
  }

  /**
   * {@inheritdoc}
   *
   * Wraps original createInstance() to add typing.
   *
   * @return \Drupal\static_build\Plugin\StaticBuilderPluginInterface
   *   A newly created static builder object instance.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   *   If the instance cannot be created, such as if the ID is invalid.
   */
  public function createInstance($plugin_id, array $configuration = []): StaticBuilderPluginInterface {
    $instance = parent::createInstance($plugin_id, $configuration);
    if ($instance instanceof StaticBuilderPluginInterface) {
      return $instance;
    }

    throw new PluginException('Plugin is not an instance of ' . StaticBuilderPluginInterface::class);
  }

  /**
   * {@inheritdoc}
   *
   * Wraps parent method to add typing.
   *
   * @return \Drupal\static_build\Plugin\StaticBuilderPluginInterface
   *   A newly created static builder object instance, or a previously
   *   instantiated one if available.
   */
  public function getInstance(array $options): StaticBuilderPluginInterface {
    $instance = parent::getInstance($options);
    if ($instance instanceof StaticBuilderPluginInterface) {
      return $instance;
    }

    throw new PluginException('Plugin is not an instance of ' . StaticBuilderPluginInterface::class);
  }

  /**
   * {@inheritdoc}
   */
  public function getLocalDefinitions(): array {
    return $this->getDefinitionsByHostMode(StaticBuilderPluginInterface::HOST_MODE_LOCAL);
  }

  /**
   * {@inheritdoc}
   */
  public function getCloudDefinitions(): array {
    return $this->getDefinitionsByHostMode(StaticBuilderPluginInterface::HOST_MODE_CLOUD);
  }

  /**
   * Filter definitions by host name.
   *
   * @param string $mode
   *   Local or cloud.
   *
   * @return array
   *   Filtered definitions
   */
  protected function getDefinitionsByHostMode(string $mode): array {
    $definitions = $this->getDefinitions();
    $definitionsByHostMode = [];
    foreach ($definitions as $pluginId => $pluginDefinition) {
      if ($pluginDefinition['host'] === $mode) {
        $definitionsByHostMode[$pluginId] = $pluginDefinition;
      }
    }
    return $definitionsByHostMode;
  }

  /**
   * {@inheritDoc}
   */
  public function validateBuilderDirStructure(StaticBuilderPluginInterface $staticBuilderPlugin): array {
    $errors = [];

    $configuration = $staticBuilderPlugin->getConfiguration();
    $runModeDir = $configuration['run-mode-dir'];
    if (is_dir($runModeDir)) {
      if (!is_writable($runModeDir)) {
        $errors[] = $this->t('The directory <code>%directory</code> exists but is not writable.', ['%directory' => $runModeDir]);
      }
    }
    else {
      $errors[] = $this->t('The directory <code>%directory</code> does not exist.', ['%directory' => $runModeDir]);
    }

    if ($staticBuilderPlugin->isLocal()) {
      $buildDir = $configuration['build-dir'];
      if (!is_dir($buildDir)) {
        $errors[] = $this->t('The directory <code>%directory</code> does not exist.', ['%directory' => $buildDir]);
      }
    }

    $logDir = $configuration['log-dir'];
    if (file_exists($logDir)) {
      if (is_dir($logDir)) {
        if (!is_writable($logDir)) {
          $errors[] = $this->t('The directory <code>%directory</code> exists but is not writable.', ['%directory' => $logDir]);
        }
      }
      else {
        $errors[] = $this->t('The path <code>%directory</code> exists but is not a directory.', ['%directory' => $logDir]);
      }
    }

    $revisionsLogFile = $configuration['revisions-log-file'];
    if (file_exists($revisionsLogFile)) {
      if (is_file($revisionsLogFile)) {
        if (!is_writable($revisionsLogFile)) {
          $errors[] = $this->t('The path <code>%path</code> exists but is not writable.', ['%path' => $revisionsLogFile]);
        }
      }
      else {
        $errors[] = $this->t('The path <code>%path</code> exists but is not a file.', ['%path' => $revisionsLogFile]);
      }
    }

    return $errors;
  }

  /**
   * {@inheritdoc}
   */
  public function validateAllDirsStructure(string $plugin_id, array $configuration = []): array {
    $builder = $this->getInstance([
      'plugin_id' => $plugin_id,
      'configuration' => $configuration,
    ]);

    $builderErrors = $this->validateBuilderDirStructure($builder);
    $releaseErrors = [];
    try {
      $releaseErrors = $builder->getReleaseManager(FALSE)
        ->validateDirStructure();
    }
    catch (StaticSuiteException $e) {
      @trigger_error('Error while validating directory structure of Release Manager for Static Builder "' . $plugin_id . '": ' . $e->getMessage(), E_USER_WARNING);
    }
    return array_merge($builderErrors, $releaseErrors);
  }

}
