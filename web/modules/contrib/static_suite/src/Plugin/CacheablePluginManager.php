<?php

namespace Drupal\static_suite\Plugin;

use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * Cacheable plugin manager.
 *
 * A plugin manager that keeps an internal cache of plugin instances.
 */
class CacheablePluginManager extends DefaultPluginManager {

  /**
   * Simple cache to store plugin objects.
   *
   * @var array
   */
  protected $cache;

  /**
   * {@inheritdoc}
   *
   * @param array $options
   *   An array with the following key/value pairs:
   *   - plugin_id: (string) The plugin id
   *   - configuration: (array) the configuration for the plugin.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   *   If the instance cannot be created, such as if the ID is invalid.
   */
  public function getInstance(array $options) {
    $plugin_id = $options['plugin_id'];
    $configuration = $options['configuration'] ?? [];

    $objectCacheKey = $plugin_id . '-' . md5(serialize($configuration));
    if (empty($this->cache[$objectCacheKey])) {
      $this->cache[$objectCacheKey] = $this->createInstance($plugin_id, $configuration);
    }
    if ($this->cache[$objectCacheKey]) {
      return $this->cache[$objectCacheKey];
    }

    return FALSE;
  }

}
