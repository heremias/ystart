<?php

namespace Drupal\Tests\gatsby_extras\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\taxonomy\Traits\TaxonomyTestTrait;
use Drupal\Tests\Traits\Core\PathAliasTestTrait;

/**
 * Defines a test for AliasLinkEnhancer.
 *
 * @group gatsby_extras
 *
 * @requires module jsonapi_extras
 */
class AliasLinkEnhancerTest extends KernelTestBase {

  use PathAliasTestTrait;
  use TaxonomyTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'filter',
    'gatsby_extras',
    'jsonapi',
    'jsonapi_extras',
    'path_alias',
    'serialization',
    'taxonomy',
    'text',
    'user',
  ];

  /**
   * The resource field enhancer.
   *
   * @var \Drupal\jsonapi_extras\Plugin\ResourceFieldEnhancerInterface
   */
  protected $enhancer;

  /**
   * The taxonomy term.
   *
   * @var \Drupal\taxonomy\TermInterface
   */
  protected $term;

  /**
   * The path alias.
   *
   * @var \Drupal\path_alias\PathAliasInterface
   */
  protected $pathAlias;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('path_alias');
    $this->installEntitySchema('taxonomy_term');

    $this->enhancer = $this->container->get('plugin.manager.resource_field_enhancer')
      ->createInstance('alias_link');

    $vocabulary = $this->createVocabulary();
    $this->term = $this->createTerm($vocabulary);
    $this->pathAlias = $this->createPathAlias($this->term->toUrl()->toString(), $this->randomMachineName());
    $this->container->get('path_processor_manager')->addOutbound($this->container->get('path_alias.path_processor'));
  }

  /**
   * Test transform.
   */
  public function testTransform() {
    $output = $this->enhancer->transform([
      'uri' => sprintf('entity:taxonomy_term/%s/%s', $this->term->bundle(), $this->term->uuid()),
    ]);
    $this->assertEquals($this->pathAlias->getAlias(), $output['uri_alias']);
  }

  /**
   * Test undoTransform.
   */
  public function testUndoTransform() {
    $output = $this->enhancer->undoTransform([
      'uri' => 'entity:taxonomy_term/' . $this->term->id(),
    ]);
    $this->assertEquals($this->pathAlias->getAlias(), $output['uri_alias']);
  }

}
