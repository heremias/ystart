<?php

namespace Drupal\static_export\Exporter\Output\Uri\Resolver\PagePath;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\static_export\Annotation\StaticPagePathUriResolver;
use Drupal\static_suite\Plugin\CacheablePluginManager;
use Traversable;

/**
 * Provides the page path to URI converter plugin manager.
 */
class PagePathUriResolverPluginManager extends CacheablePluginManager implements PagePathUriResolverPluginManagerInterface {

  /**
   * Constructs a new PagePathToUriResolverPluginManager object.
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
    parent::__construct('Plugin/static_export/Output/Uri/Resolver/PagePath', $namespaces, $module_handler, PagePathUriResolverPluginInterface::class, StaticPagePathUriResolver::class);

    $this->alterInfo('static_export_page_path_to_uri_resolver_info');
    $this->setCacheBackend($cache_backend, 'static_export_page_path_to_uri_resolver_plugins');
  }

  /**
   * {@inheritdoc}
   */
  public function createInstance($plugin_id, array $configuration = []): PagePathUriResolverPluginInterface {
    $instance = parent::createInstance($plugin_id, $configuration);
    if ($instance instanceof PagePathUriResolverPluginInterface) {
      return $instance;
    }

    throw new PluginException('Plugin is not an instance of ' . PagePathUriResolverPluginInterface::class);
  }

  /**
   * {@inheritdoc}
   */
  public function getInstance(array $options): PagePathUriResolverPluginInterface {
    $instance = parent::getInstance($options);
    if ($instance instanceof PagePathUriResolverPluginInterface) {
      return $instance;
    }

    throw new PluginException('Plugin is not an instance of ' . PagePathUriResolverPluginInterface::class);
  }

  /**
   * {@inheritdoc}
   */
  public function getDefinitionsByType(string $type): array {
    $definitions = $this->getDefinitions();
    $definitionsByType = [];
    foreach ($definitions as $pluginId => $pluginDefinition) {
      if ($pluginDefinition['type'] === $type) {
        $definitionsByType[$pluginId] = $pluginDefinition;
      }
    }
    return $definitionsByType;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefinitionsSortedByWeight(): array {
    $definitions = $this->getDefinitions();
    uasort($definitions, "self::sortByWeight");
    return $definitions;
  }

  /**
   * Sorting function for definitions, based on weight field.
   *
   * @param array $a
   *   First definition array to compare.
   * @param array $b
   *   Second definition array to compare.
   *
   * @return int
   */
  protected static function sortByWeight(array $a, array $b): int {
    if ($a['weight'] === $b['weight']) {
      return 0;
    }
    return ($a['weight'] < $b['weight']) ? -1 : 1;
  }

}
