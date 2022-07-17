<?php

namespace Drupal\static_export\Exporter\Type\Entity;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\static_export\Annotation\StaticEntityExporter;
use Drupal\static_export\Exporter\ConstrainedExporterPluginManagerBase;
use Traversable;

/**
 * Provides the EntityExporter plugin manager.
 */
class EntityExporterPluginManager extends ConstrainedExporterPluginManagerBase implements EntityExporterPluginManagerInterface {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

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
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler, ConfigFactoryInterface $config_factory) {
    parent::__construct('Plugin/static_export/Exporter/Entity', $namespaces, $module_handler, EntityExporterPluginInterface::class, StaticEntityExporter::class);

    $this->alterInfo('static_export_entity_exporter_info');
    $this->setCacheBackend($cache_backend, 'static_export_entity_exporter_plugins');
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultInstanceConfigurationName(): string {
    return 'exportable_entity.exporter';
  }

  /**
   * {@inheritdoc}
   */
  public function createDefaultInstance(): EntityExporterPluginInterface {
    $instance = $this->createInstance($this->getDefaultExporterId());
    if ($instance instanceof EntityExporterPluginInterface) {
      return $instance;
    }
    throw new PluginException('Plugin is not an instance of ' . EntityExporterPluginInterface::class);
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultInstance(): EntityExporterPluginInterface {
    $instance = $this->getInstance(['plugin_id' => $this->getDefaultExporterId()]);
    if ($instance instanceof EntityExporterPluginInterface) {
      return $instance;
    }
    throw new PluginException('Plugin is not an instance of ' . EntityExporterPluginInterface::class);
  }

}
