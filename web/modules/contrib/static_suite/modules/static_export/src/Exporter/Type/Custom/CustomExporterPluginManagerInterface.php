<?php

namespace Drupal\static_export\Exporter\Type\Custom;

use Drupal\Component\Plugin\Discovery\CachedDiscoveryInterface;
use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Cache\CacheableDependencyInterface;

/**
 * Typed interface for Custom Exporter Manager.
 */
interface CustomExporterPluginManagerInterface extends PluginManagerInterface, CachedDiscoveryInterface, CacheableDependencyInterface {

  /**
   * {@inheritdoc}
   *
   * Wraps parent method to add typing.
   *
   * @return \Drupal\static_export\Exporter\Type\Custom\CustomExporterPluginInterface
   *   A newly created exporter object instance.
   */
  public function createInstance($plugin_id, array $configuration = []): CustomExporterPluginInterface;

  /**
   * {@inheritdoc}
   *
   * Wraps parent method to add typing.
   *
   * @return \Drupal\static_export\Exporter\Type\Custom\CustomExporterPluginInterface
   *   A newly created custom exporter object instance, or a previously
   *   instantiated one if available.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   *   If the instance cannot be created, such as if the ID is invalid.
   */
  public function getInstance(array $options): CustomExporterPluginInterface;

}
