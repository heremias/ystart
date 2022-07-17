<?php

namespace Drupal\static_export\Exporter\Type\Entity;

use Drupal\Component\Plugin\Discovery\CachedDiscoveryInterface;
use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Cache\CacheableDependencyInterface;

/**
 * Typed interface for Entity Exporter Manager.
 */
interface EntityExporterPluginManagerInterface extends PluginManagerInterface, CachedDiscoveryInterface, CacheableDependencyInterface {

  /**
   * Create new instance of default entity exporter as defined by configuration.
   *
   * @return EntityExporterPluginInterface
   *   Default entity exporter.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function createDefaultInstance(): EntityExporterPluginInterface;

  /**
   * Get default entity exporter as defined by configuration.
   *
   * @return EntityExporterPluginInterface
   *   Default entity exporter.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function getDefaultInstance(): EntityExporterPluginInterface;

}
