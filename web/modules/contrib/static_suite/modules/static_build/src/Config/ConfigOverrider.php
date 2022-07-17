<?php

namespace Drupal\static_build\Config;

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
   * Constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   Config factory.
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
  public function loadOverrides($names): array {
    $overrides = [];

    if (in_array('static_build.settings', $names, TRUE)) {
      $originalBaseDir = $this->configFactory->getEditable('static_build.settings')
        ->getOriginal('base_dir', FALSE);
      if ($originalBaseDir) {
        $originalBaseDir = DRUPAL_ROOT . $originalBaseDir;
        $originalBaseDir = $this->staticSuiteUtils->removeDotSegments($originalBaseDir);
        $overrides['static_build.settings']['base_dir'] = $originalBaseDir;
      }
    }

    return $overrides;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheSuffix(): string {
    return 'static_build';
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
