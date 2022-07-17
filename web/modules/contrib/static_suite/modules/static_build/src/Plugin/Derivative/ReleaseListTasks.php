<?php

namespace Drupal\static_build\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\static_build\Plugin\StaticBuilderPluginManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines release list tasks.
 */
class ReleaseListTasks extends DeriverBase implements ContainerDeriverInterface {

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
      // Define first level (tab).
      $this->derivatives[$base_plugin_definition['id']] = array_merge(
        $base_plugin_definition,
        [
          'route_name' => $base_plugin_definition['id'] . '.default',
          'base_route' => 'static_build.admin_reports',
        ]
      );

      // Define second level tasks.
      $i = 0;
      foreach ($definitions as $definition) {
        $routeName = $base_plugin_definition['id'];
        $routeParameters = ['builderId' => $definition['id']];
        if ($i === 0) {
          $routeName .= '.default';
          $routeParameters = [];
        }
        $this->derivatives[$base_plugin_definition['id'] . '.' . $definition['id']] = array_merge(
          $base_plugin_definition,
          [
            'title' => $definition['label'],
            'route_name' => $routeName,
            'route_parameters' => $routeParameters,
            'parent_id' => $base_plugin_definition['id'] . ':' . $base_plugin_definition['id'],
          ]
        );
        $i++;
      }
    }

    return parent::getDerivativeDefinitions($base_plugin_definition);
  }

}
