<?php

namespace Drupal\static_deploy\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\static_build\Plugin\StaticBuilderPluginManagerInterface;
use Drupal\static_deploy\Plugin\StaticDeployerPluginManagerInterface;
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
   * The static deployer manager.
   *
   * @var \Drupal\static_deploy\Plugin\StaticDeployerPluginManagerInterface
   */
  protected $staticDeployerPluginManager;

  /**
   * FileViewer controller constructor.
   *
   * @param \Drupal\static_build\Plugin\StaticBuilderPluginManagerInterface $staticBuilderPluginManager
   *   The static builder manager.
   * @param \Drupal\static_deploy\Plugin\StaticDeployerPluginManagerInterface $staticDeployerPluginManager
   *   The static deployer manager.
   */
  public function __construct(
    StaticBuilderPluginManagerInterface $staticBuilderPluginManager,
    StaticDeployerPluginManagerInterface $staticDeployerPluginManager
  ) {
    $this->staticBuilderPluginManager = $staticBuilderPluginManager;
    $this->staticDeployerPluginManager = $staticDeployerPluginManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static(
      $container->get('plugin.manager.static_builder'),
      $container->get('plugin.manager.static_deployer')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    $localBuilderDefinitions = $this->staticBuilderPluginManager->getLocalDefinitions();
    $deployerDefinitions = $this->staticDeployerPluginManager->getDefinitions();

    if (is_array($localBuilderDefinitions) && count($localBuilderDefinitions) > 0 && is_array($deployerDefinitions) && count($deployerDefinitions) > 0) {
      foreach ($deployerDefinitions as $deployerDefinition) {
        $this->derivatives[$base_plugin_definition['id'] . '.' . $deployerDefinition['id']] = array_merge(
          $base_plugin_definition,
          [
            'title' => $this->t('Deployments to @deployer', ['@deployer' => $deployerDefinition['label']]),
            'route_name' => 'static_deployer_' . $deployerDefinition['id'] . '.release_list.live.default',
            'base_route' => 'static_deploy.admin_reports',
          ]
        );

        // Define second level tasks.
        $i = 0;
        foreach ($localBuilderDefinitions as $builderDefinition) {
          $routeName = 'static_deployer_' . $deployerDefinition['id'] . '.release_list.live';
          $routeParameters = ['builderId' => $builderDefinition['id']];
          if ($i === 0) {
            $routeName .= '.default';
            $routeParameters = [];
          }
          $this->derivatives[$base_plugin_definition['id'] . '.' . $deployerDefinition['id'] . '.' . $builderDefinition['id']] = array_merge(
            $base_plugin_definition,
            [
              'title' => $builderDefinition['label'],
              'route_name' => $routeName,
              'route_parameters' => $routeParameters,
              'parent_id' => $base_plugin_definition['id'] . ':' . $base_plugin_definition['id'] . '.' . $deployerDefinition['id'],
            ]
          );
          $i++;
        }
      }
    }

    return parent::getDerivativeDefinitions($base_plugin_definition);
  }

}
