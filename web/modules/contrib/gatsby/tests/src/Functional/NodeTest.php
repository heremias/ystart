<?php

namespace Drupal\Tests\gatsby\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Test the whole node process.
 *
 * @group gatsby
 */
class NodeTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'gatsby',
    'gatsby_extras',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Log in as an admin.
    $this->drupalLogin($this->createUser([
      'administer gatsby',
      'bypass node access',
      'administer content types',
      'administer nodes',
    ]));
  }

  /**
   * Test the content type configuration options.
   */
  public function testContentTypeSettings() {
    // Load the content type creation page.
    $this->drupalGet('admin/structure/types/add');
    $session = $this->assertSession();
    $session->statusCodeEquals(200);

    // Confirm that the Gatsby options are not present yet as it has not been
    // enabled for the node entity type.
    $session->fieldNotExists('gatsby_preview');
    $session->fieldNotExists('gatsby_preview_target');

    // Create the content type.
    $edit = [
      'name' => 'Article',
      'type' => 'article',
    ];
    $this->submitForm($edit, 'Save content type');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('The content type Article has been added.');

    // Loads the node form.
    $this->drupalGet('node/add/article');
    $this->assertSession()->statusCodeEquals(200);

    // Confirm that the preview button does not exist.
    $this->assertSession()->buttonNotExists('Open Gatsby Preview');

    // Save the node.
    $edit = [
      'title[0][value]' => 'Testing Gatsby',
      'body[0][value]' => 'Testing the Gatsby system.',
      // This must be unpublished.
      'status[value]' => FALSE,
    ];
    $this->submitForm($edit, 'Save');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Article Testing Gatsby has been created');
    $this->assertSession()->pageTextContains('Testing the Gatsby system.');

    // Edit the node, which is expected to be node #1.
    $this->drupalGet('node/1/edit');
    $this->assertSession()->statusCodeEquals(200);

    // Confirm that the preview button still does not exist.
    $this->assertSession()->buttonNotExists('Open Gatsby Preview');

    // Define some Gatsby settings.
    $this->drupalGet('admin/config/services/gatsby/settings');
    $this->assertSession()->statusCodeEquals(200);
    $edit = [
      // @todo Fill in the current hostname with some URL values.
      'server_url' => sprintf('https://%s.com', $this->randomMachineName()),
      'preview_callback_url' => sprintf('https://%s.com', $this->randomMachineName()),
      'incrementalbuild_url' => sprintf('https://%s.com', $this->randomMachineName()),
      'contentsync_url' => sprintf('https://%s.com', $this->randomMachineName()),
      'build_published' => FALSE,
      'supported_entity_types[node]' => TRUE,
      'log_json' => TRUE,
    ];
    $this->submitForm($edit, 'Save configuration');
    $this->assertSession()->statusCodeEquals(200);

    // @todo Verify the settings were saved.
    $this->assertSession()->pageTextContains('The configuration options have been saved.');

    // Load the content type creation page again.
    $this->drupalGet('admin/structure/types/add');
    $this->assertSession()->statusCodeEquals(200);

    // Confirm that the Gatsby options are now present.
    $this->assertSession()->fieldExists('gatsby_preview');
    $this->assertSession()->fieldExists('gatsby_preview_target');

    // Enable Gatsby for the Article content type.
    $this->drupalGet('admin/structure/types/manage/article');
    $this->assertSession()->statusCodeEquals(200);
    $edit = [
      'gatsby_preview' => TRUE,
    ];
    $this->submitForm($edit, 'Save content type');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('The content type Article has been updated.');

    // Loads the node form.
    $this->drupalGet('node/add/article');
    $this->assertSession()->statusCodeEquals(200);

    // Confirm that the preview button does not exist.
    $this->assertSession()->buttonNotExists('Open Gatsby Preview');

    // Save the node.
    $edit = [
      'title[0][value]' => 'Testing Gatsby',
      'body[0][value]' => 'Testing the Gatsby system.',
      // This must be unpublished.
      'status[value]' => FALSE,
    ];
    $this->submitForm($edit, 'Save');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Article Testing Gatsby has been created');
    $this->assertSession()->pageTextContains('Testing the Gatsby system.');

    // Edit the node, which is expected to be node #2.
    $this->drupalGet('node/2/edit');
    $this->assertSession()->statusCodeEquals(200);

    // Confirm that the preview button does exist.
    $this->assertSession()->buttonExists('Open Gatsby Preview');
  }

}
