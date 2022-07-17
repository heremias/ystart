<?php

namespace Drupal\static_export_data_resolver_graphql\Config;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ConfigFactoryOverrideInterface;
use Drupal\Core\Config\StorageInterface;
use Drupal\static_suite\Utility\StaticSuiteUtilsInterface;

/**
 * Overrides config to transform relative paths into absolute ones.
 */
class ConfigOverrider implements ConfigFactoryOverrideInterface {

  /**
   * Config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Static Suite utils.
   *
   * @var \Drupal\static_suite\Utility\StaticSuiteUtilsInterface
   */
  protected $staticSuiteUtils;

  /**
   * {@inheritdoc}
   *
   * @param \Drupal\static_suite\Utility\StaticSuiteUtilsInterface $static_suite_utils
   *   Static Suite utils.
   */
  public function __construct(ConfigFactoryInterface $configFactory, StaticSuiteUtilsInterface $static_suite_utils) {
    $this->configFactory = $configFactory;
    $this->staticSuiteUtils = $static_suite_utils;
  }

  /**
   * {@inheritdoc}
   */
  public function loadOverrides($names) {
    $overrides = [];

    if (in_array('static_export_data_resolver_graphql.settings', $names, TRUE)) {
      $originalGraphQLDir = $this->configFactory->getEditable('static_export_data_resolver_graphql.settings')
        ->getOriginal('dir', FALSE);
      if ($originalGraphQLDir) {
        $originalGraphQLDir = DRUPAL_ROOT . $originalGraphQLDir;
        $originalGraphQLDir = $this->staticSuiteUtils->removeDotSegments($originalGraphQLDir);
        $overrides['static_export_data_resolver_graphql.settings']['dir'] = $originalGraphQLDir;
      }
    }

    return $overrides;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheSuffix() {
    return 'static_export_data_resolver_graphql';
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheableMetadata($name) {
    return new CacheableMetadata();
  }

  /**
   * {@inheritdoc}
   */
  public function createConfigObject($name, $collection = StorageInterface::DEFAULT_COLLECTION) {
    return NULL;
  }

}
