<?php

namespace Drupal\static_export_stream_wrapper_local\Config;

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
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
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

    if (in_array('static_export_stream_wrapper_local.settings', $names, TRUE)) {
      $originalDataDir = $this->configFactory->getEditable('static_export_stream_wrapper_local.settings')
        ->getOriginal('data_dir', FALSE);
      if ($originalDataDir) {
        $originalDataDir = DRUPAL_ROOT . $originalDataDir;
        $originalDataDir = $this->staticSuiteUtils->removeDotSegments($originalDataDir);
        $overrides['static_export_stream_wrapper_local.settings']['data_dir'] = $originalDataDir;
      }
    }

    return $overrides;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheSuffix() {
    return 'static_export_stream_wrapper_local';
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
