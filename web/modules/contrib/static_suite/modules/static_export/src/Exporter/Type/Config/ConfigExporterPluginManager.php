<?php

namespace Drupal\static_export\Exporter\Type\Config;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\static_export\Annotation\StaticConfigExporter;
use Drupal\static_export\Exporter\ConstrainedExporterPluginManagerBase;
use Traversable;

/**
 * Provides the ConfigExporter plugin manager.
 */
class ConfigExporterPluginManager extends ConstrainedExporterPluginManagerBase implements ConfigExporterPluginManagerInterface {

  /**
   * Constructs a new ConfigExporterManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler, ConfigFactoryInterface $config_factory) {
    parent::__construct('Plugin/static_export/Exporter/Config', $namespaces, $module_handler, ConfigExporterPluginInterface::class, StaticConfigExporter::class);

    $this->alterInfo('static_export_config_exporter_info');
    $this->setCacheBackend($cache_backend, 'static_export_config_exporter_plugins');
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultInstanceConfigurationName(): string {
    return 'exportable_config.exporter';
  }

  /**
   * {@inheritdoc}
   */
  public function createDefaultInstance(): ConfigExporterPluginInterface {
    $instance = $this->createInstance($this->getDefaultExporterId());
    if ($instance instanceof ConfigExporterPluginInterface) {
      return $instance;
    }
    throw new PluginException('Plugin is not an instance of ' . ConfigExporterPluginInterface::class);
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultInstance(): ConfigExporterPluginInterface {
    $instance = $this->getInstance(['plugin_id' => $this->getDefaultExporterId()]);
    if ($instance instanceof ConfigExporterPluginInterface) {
      return $instance;
    }
    throw new PluginException('Plugin is not an instance of ' . ConfigExporterPluginInterface::class);
  }

}
