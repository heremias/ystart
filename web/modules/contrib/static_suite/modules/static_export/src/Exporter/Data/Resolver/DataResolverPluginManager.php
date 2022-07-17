<?php

namespace Drupal\static_export\Exporter\Data\Resolver;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\static_export\Annotation\StaticDataResolver;
use Drupal\static_suite\Plugin\CacheablePluginManager;
use Traversable;

/**
 * Provides the Data Resolver plugin manager.
 */
class DataResolverPluginManager extends CacheablePluginManager implements DataResolverPluginManagerInterface {

  /**
   * Simple cache to store plugin objects.
   *
   * @var array
   */
  protected $cache;

  /**
   * Constructs a new StaticFormatterManager object.
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
    parent::__construct('Plugin/static_export/Data/Resolver', $namespaces, $module_handler, DataResolverPluginInterface::class, StaticDataResolver::class);

    $this->alterInfo('static_export_data_resolver_info');
    $this->setCacheBackend($cache_backend, 'static_export_data_resolver_plugins');
  }

  /**
   * {@inheritdoc}
   *
   * Wraps parent method to add typing.
   *
   * @return \Drupal\static_export\Exporter\Data\Resolver\DataResolverPluginInterface
   *   A newly created resolver object instance.
   */
  public function createInstance($plugin_id, array $configuration = []): DataResolverPluginInterface {
    $instance = parent::createInstance($plugin_id, $configuration);
    if ($instance instanceof DataResolverPluginInterface) {
      return $instance;
    }

    throw new PluginException('Plugin is not an instance of ' . DataResolverPluginInterface::class);
  }

  /**
   * {@inheritdoc}
   *
   * Wraps parent method to add typing.
   *
   * @return \Drupal\static_export\Exporter\Data\Resolver\DataResolverPluginInterface
   *   A newly created resolver object instance, or a previously
   *   instantiated one if available.
   */
  public function getInstance(array $options): DataResolverPluginInterface {
    $instance = parent::getInstance($options);
    if ($instance instanceof DataResolverPluginInterface) {
      return $instance;
    }

    throw new PluginException('Plugin is not an instance of ' . DataResolverPluginInterface::class);
  }

  /**
   * Get a list of data resolver ids that export raw data.
   *
   * These exporters return data using an array that can be later formatted to
   * different formats.
   *
   * @return string[]
   *   Array of data resolvers ids that export raw data.
   */
  public function getDataResolverIdsThatExportRawData(): array {
    return $this->getDataResolverIdsFilteredByFormatDefinition(TRUE);
  }

  /**
   * Get a list of data resolver ids that export formatted data.
   *
   * These exporters return data using a specific format and cannot be later
   * formatted.
   *
   * @return string[]
   *   Array of data resolvers ids that export raw data.
   */
  public function getDataResolverIdsThatExportFormattedData(): array {
    return $this->getDataResolverIdsFilteredByFormatDefinition(FALSE);
  }

  /**
   * Get a list of data resolver ids filtered by their format definition.
   *
   * These exporters return data using a specific format and cannot be later
   * formatted.
   *
   * @param bool $isEmpty
   *   If TRUE, returns all exporter ids that contain an empty format.
   *   If FALSE, returns all exporter ids that contain a non empty format.
   *
   * @return string[]
   *   Array of data resolvers ids that export raw data.
   */
  protected function getDataResolverIdsFilteredByFormatDefinition(bool $isEmpty): array {
    $dataResolverDefinitions = $this->getDefinitions();
    $dataResolverIds = [];
    foreach ($dataResolverDefinitions as $formatterDefinition) {
      if (empty($formatterDefinition['format']) === $isEmpty) {
        $dataResolverIds[] = $formatterDefinition['id'];
      }
    }
    return $dataResolverIds;
  }

}
