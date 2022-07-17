<?php

namespace Drupal\static_deploy\Plugin;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\static_deploy\Annotation\StaticDeployer;
use Drupal\static_suite\Plugin\CacheablePluginManager;
use Traversable;

/**
 * Provides the Static Deployer plugin manager.
 */
class StaticDeployerPluginManager extends CacheablePluginManager implements StaticDeployerPluginManagerInterface {

  /**
   * Constructs a new StaticDeployerManager object.
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
    parent::__construct('Plugin/static_deploy/StaticDeployer', $namespaces, $module_handler, StaticDeployerPluginInterface::class, StaticDeployer::class);

    $this->alterInfo('static_deploy_static_deployer_info');
    $this->setCacheBackend($cache_backend, 'static_deploy_static_deployer_plugins');
  }

  /**
   * {@inheritdoc}
   *
   * Wraps original createInstance() to add typing.
   *
   * @return \Drupal\static_deploy\Plugin\StaticDeployerPluginInterface
   *   A newly created static deployer object instance.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   *   If the instance cannot be created, such as if the ID is invalid.
   */
  public function createInstance($plugin_id, array $configuration = []): StaticDeployerPluginInterface {
    $instance = parent::createInstance($plugin_id, $configuration);
    if ($instance instanceof StaticDeployerPluginInterface) {
      return $instance;
    }

    throw new PluginException('Plugin is not an instance of ' . StaticDeployerPluginInterface::class);
  }

  /**
   * {@inheritdoc}
   *
   * Wraps parent method to add typing.
   *
   * @return \Drupal\static_deploy\Plugin\StaticDeployerPluginInterface
   *   A newly created static builder object instance, or a previously
   *   instantiated one if available.
   */
  public function getInstance(array $options): StaticDeployerPluginInterface {
    $instance = parent::getInstance($options);
    if ($instance instanceof StaticDeployerPluginInterface) {
      return $instance;
    }

    throw new PluginException('Plugin is not an instance of ' . StaticDeployerPluginInterface::class);
  }

}
