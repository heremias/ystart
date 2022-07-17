<?php

namespace Drupal\static_export\Exporter\Type\Config;

use Drupal\Component\Plugin\Discovery\CachedDiscoveryInterface;
use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Cache\CacheableDependencyInterface;

/**
 * Typed interface for Config Exporter Manager.
 */
interface ConfigExporterPluginManagerInterface extends PluginManagerInterface, CachedDiscoveryInterface, CacheableDependencyInterface {

  /**
   * Create new instance of default config exporter as defined by configuration.
   *
   * @return ConfigExporterPluginInterface
   *   Default config exporter.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function createDefaultInstance(): ConfigExporterPluginInterface;

  /**
   * Get default config exporter as defined by configuration.
   *
   * @return ConfigExporterPluginInterface
   *   Default config exporter.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function getDefaultInstance(): ConfigExporterPluginInterface;

}
