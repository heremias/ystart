<?php

namespace Drupal\Tests\decoupled_pages;

use Drupal\TestSite\TestSetupInterface;

/**
 * Nightwatch setup file.
 */
class NightwatchTestSetupFile implements TestSetupInterface {

  /**
   * {@inheritdoc}
   */
  public function setup() {
    \Drupal::service('module_installer')->install(['decoupled_pages_test']);
  }

}
