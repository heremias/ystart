<?php

namespace Drupal\static_export\Exporter;

use Drupal\Component\Plugin\Discovery\CachedDiscoveryInterface;
use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Cache\CacheableDependencyInterface;

/**
 * Interface for exporter managers that are constrained by some configuration.
 *
 * Constrained exporter managers can only return an instance of one, and only
 * one, exporter plugin. This is the case for non-custom exporters such as
 * the entity or config exporter.
 */
interface ConstrainedExporterPluginManagerInterface extends PluginManagerInterface, CachedDiscoveryInterface, CacheableDependencyInterface {

  /**
   * Get the configuration name where the default plugin id is defined.
   *
   * Inside static_export.settings, a name (e.g.- exportable_entity.exporter)
   * that contains the default plugin id.
   *
   * @return string
   *   Configuration name where the default plugin id is defined.
   */
  public function getDefaultInstanceConfigurationName(): string;

  /**
   * Create new instance of default exporter as defined by configuration.
   *
   * @return ExporterPluginInterface
   *   Default exporter.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function createDefaultInstance(): ExporterPluginInterface;

  /**
   * Get default exporter as defined by configuration.
   *
   * @return ExporterPluginInterface
   *   Default exporter.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function getDefaultInstance(): ExporterPluginInterface;

}
