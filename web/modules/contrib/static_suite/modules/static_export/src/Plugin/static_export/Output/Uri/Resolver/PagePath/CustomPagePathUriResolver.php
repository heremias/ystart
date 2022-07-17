<?php

namespace Drupal\static_export\Plugin\static_export\Output\Uri\Resolver\PagePath;

use Drupal\Component\Plugin\PluginBase;
use Drupal\static_export\Exporter\Output\Uri\Resolver\PagePath\PagePathUriResolverPluginInterface;
use Drupal\static_export\Exporter\Output\Uri\UriInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a page path URI resolver for custom exporters.
 *
 * @StaticPagePathUriResolver (
 *  id = "custom",
 *  label = "Custom page path URI resolver",
 *  description = "Default custom page path URI resolver provided by Static
 *   Export", type = "custom", weight = 10,
 * )
 */
class CustomPagePathUriResolver extends PluginBase implements PagePathUriResolverPluginInterface {

  /**
   * Drupal cache.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The entity exporter URI resolver.
   *
   * @var \Drupal\static_export\Exporter\Type\Custom\CustomExporterPluginManagerInterface
   */
  protected $customExporterManager;

  /**
   * The URI factory.
   *
   * @var \Drupal\static_export\Exporter\Output\Uri\UriFactory|object|null
   */
  protected $uriFactory;

  /**
   * A simple cache to avoid running the resolver twice for the same resource.
   *
   * @var \Drupal\static_export\Exporter\Output\Uri\UriInterface[]
   */
  protected $localUriCache;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->cache = $container->get("cache.default");
    $instance->languageManager = $container->get("language_manager");
    $instance->uriFactory = $container->get("static_export.uri_factory");
    $instance->customExporterManager = $container->get("plugin.manager.static_custom_exporter");
    return $instance;
  }

  /**
   * {@inheritdoc}
   *
   * It uses an internal cache ($this->uriCache) to avoid running the resolver
   * for the same pagePath. It also uses Drupal's cache to avoid having to
   * instantiate all exporters on every hit.
   */
  public function resolve(string $pagePath, string $langcode): ?UriInterface {
    $localUriCacheKey = $pagePath . '---' . $langcode;
    if (!isset($this->localUriCache[$localUriCacheKey])) {

      $cid = 'static_export:supported-page-paths:' . $langcode;
      $cachedSupportedPagePaths = $this->cache->get($cid);
      if ($cachedSupportedPagePaths && is_array($cachedSupportedPagePaths->data)) {
        $uri = $this->getMatchingUri($cachedSupportedPagePaths->data, $pagePath);
      }
      else {
        $customExporterDefinitions = $this->customExporterManager->getDefinitions();
        $uri = NULL;
        $allSupportedPagePaths = [];
        foreach ($customExporterDefinitions as $customExporterDefinition) {
          $exporter = $this->customExporterManager->getInstance(['plugin_id' => $customExporterDefinition['id']]);
          $supportedPagePaths = $exporter->getSupportedPagePaths($langcode);
          $allSupportedPagePaths[] = $supportedPagePaths;
          $uri = $this->getMatchingUri($supportedPagePaths, $pagePath);
          if ($uri) {
            break;
          }
        }
        $allSupportedPagePaths = array_reduce($allSupportedPagePaths, 'array_merge', []);
        $this->cache->set($cid, $allSupportedPagePaths);
      }

      $this->localUriCache[$localUriCacheKey] = $uri;
    }

    return $this->localUriCache[$localUriCacheKey];
  }

  /**
   * @param array $supportedPagePaths
   *   Array of supported paths.
   * @param string $pagePath
   *   Page path to serach for inside $supportedPagePaths.
   *
   * @return \Drupal\static_export\Exporter\Output\Uri\UriInterface|null
   *   An URI if something found, or NULL otherwise.
   * @see CustomExporterPluginInterface::getSupportedPagePaths()
   */
  protected function getMatchingUri(array $supportedPagePaths, string $pagePath): ?UriInterface {
    foreach ($supportedPagePaths as $pagePathPattern => $dataSourceUriTarget) {
      if (preg_match("/$pagePathPattern/", $pagePath)) {
        // Replace possible references from $pagePathPattern.
        $dataSourceUriTarget = preg_replace("/$pagePathPattern/", $dataSourceUriTarget, $pagePath);
        return $this->uriFactory->create($dataSourceUriTarget);
      }
    }
    return NULL;
  }

}
