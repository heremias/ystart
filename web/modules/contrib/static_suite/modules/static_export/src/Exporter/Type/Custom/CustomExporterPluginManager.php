<?php

namespace Drupal\static_export\Exporter\Type\Custom;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\static_export\Annotation\StaticCustomExporter;
use Drupal\static_suite\Plugin\CacheablePluginManager;
use Traversable;

/**
 * Provides the CustomExporter plugin manager.
 */
class CustomExporterPluginManager extends CacheablePluginManager implements CustomExporterPluginManagerInterface {

  /**
   * Constructs a new EntityExporterManager object.
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
    parent::__construct('Plugin/static_export/Exporter/Custom', $namespaces, $module_handler, CustomExporterPluginInterface::class, StaticCustomExporter::class);

    $this->alterInfo('static_export_custom_exporter_info');
    $this->setCacheBackend($cache_backend, 'static_export_custom_exporter_plugins');
  }

  /**
   * {@inheritdoc}
   */
  public function createInstance($plugin_id, array $configuration = []): CustomExporterPluginInterface {
    $instance = parent::createInstance($plugin_id, $configuration);
    if ($instance instanceof CustomExporterPluginInterface) {
      return $instance;
    }

    throw new PluginException('Plugin is not an instance of ' . CustomExporterPluginInterface::class);
  }

  /**
   * {@inheritdoc}
   */
  public function getInstance(array $options): CustomExporterPluginInterface {
    $instance = parent::getInstance($options);
    if ($instance instanceof CustomExporterPluginInterface) {
      return $instance;
    }

    throw new PluginException('Plugin is not an instance of ' . CustomExporterPluginInterface::class);
  }

}
