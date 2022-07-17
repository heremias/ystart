<?php

namespace Drupal\Tests\content_as_config\Functional;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\taxonomy\TermInterface;
use Drupal\Tests\BrowserTestBase;
use Drupal\user\UserInterface;

/**
 * Tests the taxonomy-terms import/export UI.
 */
class TaxonomyTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'content_as_config',
    'taxonomy',
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
   * Our vocabulary ID.
   *
   * @var string
   */
  protected string $vid;

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
      'administer taxonomy',
      'administer site configuration',
    ]);

    $this->entityTypeManager = $this->container->get('entity_type.manager');

    $this->vid = strtolower($this->randomMachineName(16));
    $parameters = [
      'name' => 'Test Vocabulary ' . $this->randomMachineName(),
      'vid' => $this->vid,
      'description' => '',
    ];
    $vocab = $this->entityTypeManager->getStorage('taxonomy_vocabulary')->create($parameters);
    $vocab->save();
  }

  /**
   * Tests export and import of taxonomy terms.
   */
  public function testTaxonomyTermImportExport() {
    // Log in the user.
    $this->drupalLogin($this->adminUser);

    $add_term_url = 'admin/structure/taxonomy/manage/' . $this->vid . '/add';

    $this->drupalGet($add_term_url);
    $term_name = 'term_' . $this->randomMachineName();
    $term_desc = $this->randomMachineName(100);
    $this->drupalGet($add_term_url);
    $edit = [
      'name[0][value]' => $term_name,
      'description[0][value]' => $term_desc,
      'parent[]' => [0],
    ];
    $this->submitForm($edit, t('Save'));

    $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
    $term_entities = $term_storage->loadByProperties([
      'vid' => $this->vid,
      'name' => $term_name,
    ]);
    /** @var \Drupal\taxonomy\Entity\Term $term_entity */
    $term_entity = reset($term_entities);
    $this->assertTrue($term_entity instanceof TermInterface, 'Taxonomy term failed to be created');
    $term_uuid = $term_entity->uuid();

    $this->drupalGet('admin/structure/content-as-config/taxonomies-export');
    $edit = ['export_list[' . $this->vid . ']' => $this->vid];
    $this->submitForm($edit, t('Export'));

    $config = $this->config('content_as_config.taxonomy_term');
    $term_config_item = $config->get($term_uuid);
    $this->assertNotEmpty($term_config_item, 'Taxonomy term configuration was not saved.');

    $this->assertSame($term_config_item['uuid'], $term_uuid, 'Taxonomy term UUID does not match.');
    $this->assertSame($term_config_item['vid'], $this->vid, 'Taxonomy term not set to correct vocabulary.');
    $this->assertSame($term_config_item['name'], $term_name, 'Taxonomy term name does not match.');
    $this->assertEquals($term_config_item['tid'], $term_entity->id(), 'Taxonomy term ID does not match.');
    // @todo Test weight.
    // @todo Test term hierarchy.

    $term_entity->delete();

    $term_entities = $term_storage->loadByProperties([
      'vid' => $this->vid,
      'name' => $term_name,
    ]);
    $this->assertEmpty($term_entities, 'Taxonomy term was not properly deleted.');

    $this->drupalGet('admin/structure/content-as-config/taxonomies-import');
    $edit = ['import_list[' . $term_uuid . ']' => $term_uuid];
    $this->submitForm($edit, t('Import'));

    $term_entities = $term_storage->loadByProperties([
      'vid' => $this->vid,
      'name' => $term_name,
    ]);
    /** @var \Drupal\taxonomy\Entity\Term $term_entity */
    $term_entity = reset($term_entities);
    $this->assertNotEmpty($term_entity, 'Taxonomy term was not properly reconstituted.');

    $this->assertEquals($term_entity->bundle(), $this->vid, 'Vocabulary was not properly set.');
    $this->assertEquals($term_entity->label(), $term_name, 'Taxonomy term name was not properly set.');
    $this->assertEquals($term_entity->id(), $term_config_item['tid'], 'Taxonomy term ID was not properly set.');
    $this->assertEquals($term_entity->getDescription(), $term_desc, 'Taxonomy term description was not properly set.');
  }

}
