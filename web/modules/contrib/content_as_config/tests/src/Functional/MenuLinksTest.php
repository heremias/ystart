<?php

namespace Drupal\Tests\content_as_config\Functional;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\menu_link_content\MenuLinkContentInterface;
use Drupal\Tests\BrowserTestBase;
use Drupal\user\UserInterface;

/**
 * Tests the menu-links import/export UI.
 */
class MenuLinksTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'content_as_config',
    'menu_ui',
    'menu_link_content',
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
   * The name of the menu to be exported/imported.
   *
   * @var string
   */
  protected string $menuName;

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
      'administer menu',
      'administer site configuration',
    ]);

    $this->menuName = strtolower($this->randomMachineName(16));

    $this->entityTypeManager = $this->container->get('entity_type.manager');
    $params = [
      'id' => $this->menuName,
      'description' => '',
      'label' => 'menu_' . $this->randomMachineName(16),
    ];

    $menu = $this->entityTypeManager->getStorage('menu')->create($params);
    $menu->save();
  }

  /**
   * Tests export and import of menu links.
   */
  public function testMenuLinkImportExport() {
    // Log in the user.
    $this->drupalLogin($this->adminUser);

    $this->drupalGet('admin/structure/menu/manage/' . $this->menuName . '/add');
    $link_title = 'menu_link_' . $this->randomString();
    $edit = [
      'link[0][uri]' => 'http://example.com/foo#bar',
      'title[0][value]' => $link_title,
    ];
    $this->submitForm($edit, t('Save'));

    $link_storage = $this->entityTypeManager->getStorage('menu_link_content');
    $link_entities = $link_storage->loadByProperties([
      'menu_name' => $this->menuName,
      'title' => $link_title,
    ]);
    /** @var \Drupal\menu_link_content\Entity\MenuLinkContent $link_entity */
    $link_entity = reset($link_entities);
    $this->assertTrue($link_entity instanceof MenuLinkContentInterface, 'Link failed to be created');
    $link_uuid = $link_entity->uuid();

    $this->drupalGet('admin/structure/content-as-config/menu-export');
    $edit = ['export_list[' . $this->menuName . ']' => $this->menuName];
    $this->submitForm($edit, t('Export'));

    $config = $this->config('content_as_config.menu_link_content');
    $link_config_item = $config->get($link_uuid);
    $this->assertNotEmpty($link_config_item, 'Link configuration was not saved.');

    $this->assertEquals($link_config_item['uuid'], $link_uuid, 'Link UUID does not match.');
    $this->assertEquals($link_config_item['menu_name'], $this->menuName, 'Link not set to correct menu.');
    $this->assertEquals($link_config_item['link'], 'http://example.com/foo#bar', 'Link should point to /node; actually points to ' . $link_config_item['link']);

    $link_entity->delete();

    $link_entities = $link_storage->loadByProperties([
      'menu_name' => $this->menuName,
      'title' => $link_title,
    ]);
    $this->assertEmpty($link_entities, 'Link was not properly deleted.');

    $this->drupalGet('admin/structure/content-as-config/menu-import');
    $edit = ['import_list[' . $link_uuid . ']' => $link_uuid];
    $this->submitForm($edit, t('Import'));

    $link_entities = $link_storage->loadByProperties([
      'menu_name' => $this->menuName,
      'title' => $link_title,
    ]);
    $link_entity = reset($link_entities);
    $this->assertNotEmpty($link_entity, 'Link was not properly reconstituted.');

    $uri = $link_entity->getUrlObject()->getUri();

    $this->assertEquals($link_entity->getMenuName(), $this->menuName, 'Link menu name was not properly set.');
    $this->assertEquals($link_entity->label(), $link_title, 'Link title was not properly set.');
    $this->assertEquals($uri, 'http://example.com/foo#bar', 'Link URI not set properly. Expected http://example.com/foo#bar, got ' . $uri);
  }

}
