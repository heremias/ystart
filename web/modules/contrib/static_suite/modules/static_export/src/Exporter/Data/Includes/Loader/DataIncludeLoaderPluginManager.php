<?php

namespace Drupal\static_export\Exporter\Data\Includes\Loader;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\static_export\Annotation\StaticDataIncludeLoader;
use Drupal\static_suite\Plugin\CacheablePluginManager;
use Traversable;

/**
 * Provides the Data include loader plugin manager.
 */
class DataIncludeLoaderPluginManager extends CacheablePluginManager implements DataIncludeLoaderPluginManagerInterface {

  /**
   * Simple cache to store plugin objects.
   *
   * @var array
   */
  protected $cache;

  /**
   * Constructs a new DataIncludeLoaderManager object.
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
    parent::__construct('Plugin/static_export/Data/IncludeLoader', $namespaces, $module_handler, DataIncludeLoaderPluginInterface::class, StaticDataIncludeLoader::class);

    $this->alterInfo('static_export_data_include_loader_info');
    $this->setCacheBackend($cache_backend, 'static_export_data_include_loader_plugins');
  }

  /**
   * {@inheritdoc}
   *
   * Wraps parent method to add typing.
   *
   * @return \Drupal\static_export\Exporter\Data\Includes\Loader\DataIncludeLoaderPluginInterface
   *   A newly created data include loader object instance.
   */
  public function createInstance($plugin_id, array $configuration = []): DataIncludeLoaderPluginInterface {
    $instance = parent::createInstance($plugin_id, $configuration);
    if ($instance instanceof DataIncludeLoaderPluginInterface) {
      return $instance;
    }

    throw new PluginException('Plugin is not an instance of ' . DataIncludeLoaderPluginInterface::class);
  }

  /**
   * {@inheritdoc}
   *
   * Wraps parent method to add typing.
   *
   * @return \Drupal\static_export\Exporter\Data\Includes\Loader\DataIncludeLoaderPluginInterface
   *   A newly created data include loader object instance, or a previously
   *   instantiated one if available.
   */
  public function getInstance(array $options): DataIncludeLoaderPluginInterface {
    $instance = parent::getInstance($options);
    if ($instance instanceof DataIncludeLoaderPluginInterface) {
      return $instance;
    }

    throw new PluginException('Plugin is not an instance of ' . DataIncludeLoaderPluginInterface::class);
  }

  /**
   * {@inheritdoc}
   */
  public function getDefinitionsByMimeType(string $mimeType): array {
    $definitions = $this->getDefinitions();
    $definitionsByType = [];
    foreach ($definitions as $pluginId => $pluginDefinition) {
      if ($pluginDefinition['mimetype'] === $mimeType) {
        $definitionsByType[$pluginId] = $pluginDefinition;
      }
    }
    return $definitionsByType;
  }

}
