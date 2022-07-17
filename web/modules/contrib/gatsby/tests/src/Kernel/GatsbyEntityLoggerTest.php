<?php

namespace Drupal\Tests\gatsby\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\Tests\content_moderation\Traits\ContentModerationTestTrait;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Defines a test for the GatsbyEntityLogger.
 *
 * @group gatsby
 *
 * @requires module jsonapi_extras
 */
class GatsbyEntityLoggerTest extends KernelTestBase {

  use ContentTypeCreationTrait;
  use UserCreationTrait;
  use ContentModerationTestTrait;

  /**
   * {@inheritdoc}
   *
   * @todo Remove in https://www.drupal.org/project/gatsby/issues/3198678
   */
  protected $strictConfigSchema = FALSE;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'gatsby',
    'path_alias',
    'serialization',
    'jsonapi',
    'jsonapi_extras',
    'field',
    'text',
    'options',
    'system',
    'user',
    'filter',
    'workflows',
    'content_moderation',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() : void {
    parent::setUp();
    $this->installConfig(['node', 'filter']);
    $this->installSchema('system', ['sequences']);
    $this->installSchema('node', ['node_access']);
    $this->installEntitySchema('node');
    $this->installEntitySchema('path_alias');
    $this->installEntitySchema('user');
    $this->createContentType(['type' => 'page']);
    $this->installEntitySchema('gatsby_log_entity');
    \Drupal::configFactory()->getEditable('gatsby.settings')
      ->set('supported_entity_types', ['node'])
      ->set('log_published', TRUE)
      ->save();
    $this->setUpCurrentUser([], [
      'access content',
    ]);
    $this->installEntitySchema('content_moderation_state');
    $workflow = $this->createEditorialWorkflow();
    $this->addEntityTypeAndBundleToWorkflow($workflow, 'node', 'page');
  }

  /**
   * Tests entity insert.
   */
  public function testEntityInsert() {
    // Confirm that the Gatsby logs are empty.
    $log_storage = \Drupal::entityTypeManager()->getStorage('gatsby_log_entity');
    $this->assertCount(0, $log_storage->loadMultiple());

    // Create an example node.
    $node = Node::create([
      'type' => 'page',
      'status' => 1,
      'title' => 'foo',
      'moderation_state' => 'published',
    ]);
    $node->save();

    // Verify that the node saved correctly.
    $this->assertNotNull($node->id());

    // Verify that two log messages were created.
    // 1 = Builds, 2 = Preview
    $log_storage->resetCache();
    $logs = $log_storage->loadMultiple();
    // dump($logs);
    $this->assertCount(2, $logs);

    // Verify the contents of the first log message, which is for the preview
    // release.
    $log = reset($logs);
    $this->assertEquals(1, $log->id->value);
    $this->assertEquals('en', $log->langcode->value);
    $this->assertEquals($node->uuid(), $log->entity_uuid->value);
    $this->assertEquals('foo', $log->title->value);
    $this->assertEquals('node', $log->entity->value);
    $this->assertEquals('page', $log->bundle->value);
    $this->assertEquals('insert', $log->action->value);
    $this->assertEquals(1, $log->published->value);
    $this->assertEquals(1, $log->preview->value);
    // @todo Confirm the JSON data.

    // Verify the contents of the second log message, which is for the full
    // published release.
    $log = next($logs);
    $this->assertEquals(2, $log->id->value);
    $this->assertEquals('en', $log->langcode->value);
    $this->assertEquals($node->uuid(), $log->entity_uuid->value);
    $this->assertEquals('foo', $log->title->value);
    $this->assertEquals('node', $log->entity->value);
    $this->assertEquals('page', $log->bundle->value);
    $this->assertEquals('insert', $log->action->value);
    $this->assertEquals(1, $log->published->value);
    $this->assertEquals(0, $log->preview->value);
    // @todo Confirm the JSON data.

    // Create a new draft.
    $node->status = 0;
    $node->moderation_state = 'draft';
    $node->setNewRevision();
    $node->save();

    // Reset the log cache to run another query against it.
    $log_storage->resetCache();

    // Confirm that there are now three log entries.
    $logs = $log_storage->loadMultiple();
    // dump($logs);
    $this->assertCount(2, $logs);

    // Verify the contents of the first record, which is the second log message,
    // and that the first logged message isn't there anymore.
    $log = reset($logs);
    $this->assertNotEquals(1, $log->id->value);
    $this->assertEquals(2, $log->id->value);
    $this->assertEquals('en', $log->langcode->value);
    $this->assertEquals($node->uuid(), $log->entity_uuid->value);
    $this->assertEquals('foo', $log->title->value);
    $this->assertEquals('node', $log->entity->value);
    $this->assertEquals('page', $log->bundle->value);
    $this->assertEquals('insert', $log->action->value);
    $this->assertEquals(1, $log->published->value);
    $this->assertEquals(0, $log->preview->value);

    // Verify the contents of the third log message, the second record, which
    // is for the new "draft" edit.
    $log = next($logs);
    $this->assertEquals(3, $log->id->value);
    $this->assertEquals('en', $log->langcode->value);
    $this->assertEquals($node->uuid(), $log->entity_uuid->value);
    $this->assertEquals('foo', $log->title->value);
    $this->assertEquals('node', $log->entity->value);
    $this->assertEquals('page', $log->bundle->value);
    $this->assertEquals('update', $log->action->value);
    // The third record was for a draft edit, so it will be an unpublished
    // preview.
    $this->assertEquals(0, $log->published->value);
    $this->assertEquals(1, $log->preview->value);

    // @todo Finish this.
    // Turn off log_published so that entities are logged even if they are not
    // published.
    // $settings = \Drupal::configFactory()->getEditable('gatsby.settings');
    // $settings->set('log_published', FALSE);
    // $settings->save();
    //
    // // Create another draft.
    // $node->status = 0;
    // $node->moderation_state = 'draft';
    // $node->setNewRevision();
    // $node->save();
    //
    // // Reset the log cache to run another query against it.
    // $log_storage->resetCache();
    //
    // // Confirm that there are now two log messages.
    // $logs = $log_storage->loadMultiple();
    // $this->assertCount(2, $logs);
    //
    // dump($logs);
    // // Get the second log message.
    // $log = reset($logs);
    // $log = reset($logs);
    //
    // // Confirm that the log message
    // $this->assertEquals('update', $log->action->value);
    // $this->assertEquals('node', $log->entity->value);
    // $this->assertEquals('page', $log->bundle->value);
    // $this->assertEquals($node->uuid(), $log->entity_uuid->value);
  }

}
