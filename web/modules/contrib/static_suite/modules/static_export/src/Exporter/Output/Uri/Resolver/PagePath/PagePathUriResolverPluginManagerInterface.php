<?php

namespace Drupal\static_export\Exporter\Output\Uri\Resolver\PagePath;

use Drupal\Component\Plugin\Discovery\CachedDiscoveryInterface;
use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Cache\CacheableDependencyInterface;

/**
 * Typed interface for "Page path to URI converter" manager.
 */
interface PagePathUriResolverPluginManagerInterface extends PluginManagerInterface, CachedDiscoveryInterface, CacheableDependencyInterface {

  /**
   * {@inheritdoc}
   *
   * Wraps parent method to add typing.
   *
   * @return PagePathUriResolverPluginInterface
   *   A newly created converter object instance.
   */
  public function createInstance($plugin_id, array $configuration = []): PagePathUriResolverPluginInterface;

  /**
   * {@inheritdoc}
   *
   * Wraps parent method to add typing.
   *
   * @return PagePathUriResolverPluginInterface
   *   A newly created converter object instance, or a previously
   *   instantiated one if available.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   *   If the instance cannot be created, such as if the ID is invalid.
   */
  public function getInstance(array $options): PagePathUriResolverPluginInterface;

  /**
   * Get the definition of plugins by its type field.
   *
   * Definitions are sorted by their "weight" field of the annotation.
   *
   * @param string $type
   *   "type" field of the StaticDataIncludeLoader annotation.
   *
   * @return mixed[]
   *   An array of plugin definitions (empty array if no definitions were
   *   found). Keys are plugin IDs.
   */
  public function getDefinitionsByType(string $type): array;

  /**
   * Get the definition of all plugins sorted by their "weight" field.
   *
   * @return mixed[]
   *   An array of plugin definitions (empty array if no definitions were
   *   found). Keys are plugin IDs.
   */
  public function getDefinitionsSortedByWeight(): array;

}
