<?php

namespace Drupal\Tests\sfc\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests methods provided by the component controller.
 *
 * @group sfc
 * @group functional
 */
class ComponentControllerTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'sfc',
    'sfc_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'classy';

  /**
   * Tests the ::build method.
   *
   * @codeCoverageIgnore
   */
  public function testBuild() {
    $this->drupalGet('/homepage');
    $this->assertText('Welcome to the homepage!');
    $this->drupalGet('/hello/Sam');
    $this->assertText('Hello Sam!');
    $this->assertSession()->responseContains('<html');
    $this->drupalGet('/no_anon_session');
    $this->assertSession()->responseContains('no session');
    $this->drupalGet('/anon_session');
    $this->assertSession()->responseContains('yes session');
  }

}
