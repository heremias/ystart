<?php

namespace Drupal\static_export\Exporter\Data\Includes\Loader;

use Drupal\Component\Plugin\Discovery\CachedDiscoveryInterface;
use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Cache\CacheableDependencyInterface;

/**
 * Typed interface for Data Include Loader plugin manager.
 */
interface DataIncludeLoaderPluginManagerInterface extends PluginManagerInterface, CachedDiscoveryInterface, CacheableDependencyInterface {

  /**
   * {@inheritdoc}
   *
   * Wraps parent method to add typing.
   *
   * @return DataIncludeLoaderPluginInterface
   *   A newly created data include loader object instance.
   */
  public function createInstance($plugin_id, array $configuration = []): DataIncludeLoaderPluginInterface;

  /**
   * {@inheritdoc}
   *
   * Wraps parent method to add typing.
   *
   * @return DataIncludeLoaderPluginInterface
   *   A newly created data include loader object instance, or a previously
   *   instantiated one if available.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   *   If the instance cannot be created, such as if the ID is invalid.
   */
  public function getInstance(array $options): DataIncludeLoaderPluginInterface;

  /**
   * Get the definition of plugins by its mimetype field.
   *
   * @param string $mimeType
   *   "mimetype" field of the StaticDataIncludeLoader annotation.
   *
   * @return mixed[]
   *   An array of plugin definitions (empty array if no definitions were
   *   found). Keys are plugin IDs.
   */
  public function getDefinitionsByMimeType(string $mimeType): array;

}
