<?php

namespace Drupal\static_export\Exporter\Type\Locale;

use Drupal\Component\Plugin\Discovery\CachedDiscoveryInterface;
use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Cache\CacheableDependencyInterface;

/**
 * Typed interface for Locale Exporter Manager.
 */
interface LocaleExporterPluginManagerInterface extends PluginManagerInterface, CachedDiscoveryInterface, CacheableDependencyInterface {

  /**
   * Create new instance of default locale exporter as defined by configuration.
   *
   * @return LocaleExporterPluginInterface
   *   Default locale exporter.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function createDefaultInstance(): LocaleExporterPluginInterface;

  /**
   * Get default locale exporter as defined by configuration.
   *
   * @return LocaleExporterPluginInterface
   *   Default locale exporter.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function getDefaultInstance(): LocaleExporterPluginInterface;

}
