<?php

namespace Drupal\static_export\Exporter\Data\Resolver;

use Drupal\Component\Plugin\Discovery\CachedDiscoveryInterface;
use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Cache\CacheableDependencyInterface;

/**
 * Typed interface for Data Resolver plugin Manager.
 */
interface DataResolverPluginManagerInterface extends PluginManagerInterface, CachedDiscoveryInterface, CacheableDependencyInterface {

  /**
   * {@inheritdoc}
   *
   * Wraps parent method to add typing.
   *
   * @return \Drupal\static_export\Exporter\Data\Resolver\DataResolverPluginInterface
   *   A newly created data resolver object instance.
   */
  public function createInstance($plugin_id, array $configuration = []): DataResolverPluginInterface;

  /**
   * {@inheritdoc}
   *
   * Wraps parent method to add typing.
   *
   * @return \Drupal\static_export\Exporter\Data\Resolver\DataResolverPluginInterface
   *   A newly created formatter object instance, or a previously
   *   instantiated one if available.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   *   If the instance cannot be created, such as if the ID is invalid.
   */
  public function getInstance(array $options): DataResolverPluginInterface;

  /**
   * Get a list of data resolver ids that export raw data.
   *
   * These exporters return data using an array that can be later formatted to
   * different formats.
   *
   * @return string[]
   *   Array of data resolvers ids that export raw data.
   */
  public function getDataResolverIdsThatExportRawData(): array;

  /**
   * Get a list of data resolver ids that export formatted data.
   *
   * These exporters return data using a specific format and cannot be later
   * formatted.
   *
   * @return string[]
   *   Array of data resolvers ids that export raw data.
   */
  public function getDataResolverIdsThatExportFormattedData(): array;

}
