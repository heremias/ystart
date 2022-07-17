<?php

namespace Drupal\Tests\content_as_config\Functional;

use Drupal\block_content\BlockContentInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Tests\BrowserTestBase;
use Drupal\user\UserInterface;

/**
 * Tests the block-content import/export UI.
 */
class BlockContentTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'content_as_config',
    'block',
    'block_content',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * An authenticated user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected UserInterface $adminUser;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->adminUser = $this->drupalCreateUser([
      'access administration pages',
      'administer blocks',
      'administer site configuration',
    ]);

    $params = [
      'id' => 'basic',
      'label' => 'basic',
      'revision' => FALSE,
    ];

    $this->entityTypeManager = $this->container->get('entity_type.manager');

    $bundle = $this->entityTypeManager->getStorage('block_content')->create($params);
    $bundle->save();
    block_content_add_body_field($bundle->id());
  }

  /**
   * Tests export and import of menu links.
   */
  public function testMenuLinkImportExport() {
    // Log in the user.
    $this->drupalLogin($this->adminUser);

    $block_label = 'Test Block ' . $this->randomMachineName();
    $block_body = $this->randomMachineName(16);

    // Create a block.
    $edit = [];
    $edit['info[0][value]'] = $block_label;
    $edit['body[0][value]'] = $block_body;
    $this->drupalGet('block/add/basic');
    $this->submitForm($edit, t('Save'));

    $bc_storage = $this->entityTypeManager->getStorage('block_content');
    $bc_entities = $bc_storage->loadByProperties(['info' => $block_label]);
    /** @var \Drupal\block_content\Entity\BlockContent $bc_entity */
    $bc_entity = reset($bc_entities);
    $this->assertTrue($bc_entity instanceof BlockContentInterface, 'Block content failed to be created');
    $bc_uuid = $bc_entity->uuid();
    $bc_id = $bc_entity->id();

    $this->drupalGet('admin/structure/content-as-config/blocks-export');
    $edit = ['export_list[' . $bc_uuid . ']' => $bc_uuid];
    $this->submitForm($edit, t('Export'));

    $config = $this->config('content_as_config.block_content');
    $bc_config_item = $config->get($bc_uuid);
    $this->assertNotEmpty($bc_config_item, 'Block content configuration was not saved.');

    $this->assertEquals($bc_config_item['uuid'], $bc_uuid, 'Block content UUID does not match.');
    $this->assertEquals($bc_config_item['id'], $bc_id, 'Block content ID does not match.');
    $this->assertEquals($bc_config_item['info'], $block_label, 'Block content label does not match.');
    // @todo Validate block body.

    $bc_entity->delete();

    $bc_entities = $bc_storage->loadByProperties(['info' => $block_label]);
    $this->assertEmpty($bc_entities, 'Block content was not properly deleted.');

    $this->drupalGet('admin/structure/content-as-config/blocks-import');
    $edit = ['import_list[' . $bc_uuid . ']' => $bc_uuid];
    $this->submitForm($edit, t('Import'));

    $bc_entities = $bc_storage->loadByProperties(['info' => $block_label]);
    $bc_entity = reset($bc_entities);
    $this->assertNotEmpty($bc_entity, 'Block content was not properly reconstituted.');

    $this->assertEquals($bc_entity->id(), $bc_id, 'Block content ID was not properly set.');
    $this->assertEquals($bc_entity->label(), $block_label, 'Link title was not properly set.');
    // @todo Validate block body.
  }

}
