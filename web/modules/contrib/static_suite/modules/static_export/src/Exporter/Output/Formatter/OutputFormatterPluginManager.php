<?php

namespace Drupal\static_export\Exporter\Output\Formatter;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\static_export\Annotation\StaticOutputFormatter;
use Drupal\static_suite\Plugin\CacheablePluginManager;
use Traversable;

/**
 * Provides the Static Output Formatter plugin manager.
 */
class OutputFormatterPluginManager extends CacheablePluginManager implements OutputFormatterPluginManagerInterface {

  /**
   * Constructs a new OutputFormatterManager object.
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
    parent::__construct('Plugin/static_export/Output/Formatter', $namespaces, $module_handler, OutputFormatterPluginInterface::class, StaticOutputFormatter::class);

    $this->alterInfo('static_export_output_formatter_info');
    $this->setCacheBackend($cache_backend, 'static_export_output_formatter_plugins');
  }

  /**
   * {@inheritdoc}
   */
  public function createInstance($plugin_id, array $configuration = []): OutputFormatterPluginInterface {
    $instance = parent::createInstance($plugin_id, $configuration);
    if ($instance instanceof OutputFormatterPluginInterface) {
      return $instance;
    }

    throw new PluginException('Plugin is not an instance of ' . OutputFormatterPluginInterface::class);
  }

  /**
   * {@inheritdoc}
   */
  public function getInstance(array $options): OutputFormatterPluginInterface {
    $instance = parent::getInstance($options);
    if ($instance instanceof OutputFormatterPluginInterface) {
      return $instance;
    }

    throw new PluginException('Plugin is not an instance of ' . OutputFormatterPluginInterface::class);
  }

}
