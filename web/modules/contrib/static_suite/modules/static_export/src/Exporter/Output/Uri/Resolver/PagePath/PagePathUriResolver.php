<?php

namespace Drupal\static_export\Exporter\Output\Uri\Resolver\PagePath;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\static_export\Exporter\Output\Uri\UriInterface;

/**
 * URI resolver of page paths.
 */
class PagePathUriResolver implements PagePathUriResolverInterface {

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The URI resolver for page paths.
   *
   * @var \Drupal\static_export\Exporter\Output\Uri\Resolver\PagePath\PagePathUriResolverPluginManagerInterface
   */
  protected $pagePathUriResolverPluginManager;

  /**
   * An internal cache to avoid running the resolver twice.
   *
   * @var array
   */
  protected $cache;

  /**
   * PagePathUriResolver constructor.
   *
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager.
   * @param \Drupal\static_export\Exporter\Output\Uri\Resolver\PagePath\PagePathUriResolverPluginManagerInterface $pagePathUriResolverPluginManager
   *   The URI resolver for page paths.
   */
  public function __construct(LanguageManagerInterface $languageManager, PagePathUriResolverPluginManagerInterface $pagePathUriResolverPluginManager) {
    $this->languageManager = $languageManager;
    $this->pagePathUriResolverPluginManager = $pagePathUriResolverPluginManager;
  }

  /**
   * {@inheritdoc}
   */
  public function resolve(string $pagePath, string $langcode = NULL): ?UriInterface {
    // If no language is specified we default to the default language.
    $langcode = $langcode ?: $this->languageManager->getDefaultLanguage()
      ->getId();

    $cacheKey = $pagePath . '---' . $langcode;

    if (!isset($this->cache[$cacheKey])) {
      // Set default values for the cache.
      $this->cache[$cacheKey] = [
        'uri' => NULL,
        'resolver-type' => NULL,
      ];

      $definitions = $this->pagePathUriResolverPluginManager->getDefinitionsSortedByWeight();
      foreach ($definitions as $definition) {
        try {
          $resolverInstance = $this->pagePathUriResolverPluginManager->getInstance(['plugin_id' => $definition['id']]);
          $uri = $resolverInstance->resolve($pagePath, $langcode);
        }
        catch (PluginException $e) {
          continue;
        }
        if ($uri) {
          // Update cache with valid values.
          $this->cache[$cacheKey] = [
            'uri' => $uri,
            'resolver-type' => $definition['type'],
          ];
          break;
        }
      }
    }

    return $this->cache[$cacheKey]['uri'];
  }

  /**
   * {@inheritdoc}
   */
  public function isCustomPath(string $pagePath, string $langcode = NULL): bool {
    $this->resolve($pagePath, $langcode);
    $cacheKey = $pagePath . '---' . $langcode;
    return isset($this->cache[$cacheKey]) ? $this->cache[$cacheKey]['resolver-type'] === 'custom' : FALSE;
  }

}
