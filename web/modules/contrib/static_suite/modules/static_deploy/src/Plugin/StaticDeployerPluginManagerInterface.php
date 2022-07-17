<?php

namespace Drupal\static_deploy\Plugin;

use Drupal\Component\Plugin\Discovery\CachedDiscoveryInterface;
use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Cache\CacheableDependencyInterface;

/**
 * Typed interface for Static Deployer Manager.
 */
interface StaticDeployerPluginManagerInterface extends PluginManagerInterface, CachedDiscoveryInterface, CacheableDependencyInterface {

  /**
   * {@inheritdoc}
   *
   * Wraps parent method to add typing.
   *
   * @return \Drupal\static_deploy\Plugin\StaticDeployerPluginInterface
   *   A newly created exporter object instance.
   */
  public function createInstance($plugin_id, array $configuration = []): StaticDeployerPluginInterface;

  /**
   * {@inheritdoc}
   *
   * Wraps parent method to add typing.
   *
   * @return \Drupal\static_deploy\Plugin\StaticDeployerPluginInterface
   *   A newly created exporter object instance, or a previously
   *   instantiated one if available.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   *   If the instance cannot be created, such as if the ID is invalid.
   * @throws \Drupal\static_suite\StaticSuiteUserException
   */
  public function getInstance(array $options): StaticDeployerPluginInterface;

}
