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
class ReleaseListMenu extends DeriverBase implements ContainerDeriverInterface {

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
    $builderDefinitions = $this->staticBuilderPluginManager->getLocalDefinitions();
    $deployerDefinitions = $this->staticDeployerPluginManager->getDefinitions();
    if (is_array($builderDefinitions) && count($builderDefinitions) > 0 && is_array($deployerDefinitions) && count($deployerDefinitions) > 0) {
      foreach ($deployerDefinitions as $deployerDefinition) {
        $this->derivatives[$base_plugin_definition['id'] . '.' . $deployerDefinition['id']] = array_merge(
          $base_plugin_definition,
          [
            'title' => $deployerDefinition['label'],
            'route_name' => 'static_deployer_' . $deployerDefinition['id'] . '.release_list.live.default',
            'description' => $this->t('List of releases deployed to @deployer', ['@deployer' => $deployerDefinition['label']]),
            'parent' => 'static_deploy.reports.main',
          ]
        );
      }
    }

    return parent::getDerivativeDefinitions($base_plugin_definition);
  }

}
