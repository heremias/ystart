<?php

namespace Drupal\static_build\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\static_build\Plugin\StaticBuilderPluginManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines menu items for release list.
 */
class ReleaseListMenuItems extends DeriverBase implements ContainerDeriverInterface {

  use StringTranslationTrait;

  /**
   * The static builder manager.
   *
   * @var \Drupal\static_build\Plugin\StaticBuilderPluginManagerInterface
   */
  protected $staticBuilderPluginManager;

  /**
   * Constructor.
   *
   * @param \Drupal\static_build\Plugin\StaticBuilderPluginManagerInterface $staticBuilderPluginManager
   *   The static builder manager.
   */
  public function __construct(
    StaticBuilderPluginManagerInterface $staticBuilderPluginManager
  ) {
    $this->staticBuilderPluginManager = $staticBuilderPluginManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static(
      $container->get('plugin.manager.static_builder')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    if ($base_plugin_definition['id'] === 'static_build.release_list.preview') {
      $definitions = $this->staticBuilderPluginManager->getLocalDefinitions();
    }
    else {
      $definitions = $this->staticBuilderPluginManager->getDefinitions();
    }

    if (is_array($definitions) && count($definitions) > 0) {
      $this->derivatives[$base_plugin_definition['id']] = array_merge(
        $base_plugin_definition,
        [
          'parent' => 'static_build.reports.main',
          'description' => 'List of available LIVE releases',
          'route_name' => $base_plugin_definition['id'] . '.default',
        ]
      );
    }

    return parent::getDerivativeDefinitions($base_plugin_definition);
  }

}
