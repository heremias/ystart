<?php

namespace Drupal\static_deploy\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\static_build\Plugin\StaticBuilderPluginManagerInterface;
use Drupal\static_deploy\Plugin\StaticDeployerPluginManagerInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Listens to the dynamic route events.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * Static Builder Manager.
   *
   * @var \Drupal\static_build\Plugin\StaticBuilderPluginManagerInterface
   */
  protected $staticBuilderPluginManager;

  /**
   * Static Deployer Manager.
   *
   * @var \Drupal\static_deploy\Plugin\StaticDeployerPluginManagerInterface
   */
  protected $staticDeployerPluginManager;

  /**
   * Constructs the subscriber.
   *
   * @param \Drupal\static_build\Plugin\StaticBuilderPluginManagerInterface $staticBuilderPluginManager
   *   Static Builder Manager.
   * @param \Drupal\static_deploy\Plugin\StaticDeployerPluginManagerInterface $staticDeployerPluginManager
   *   Static Deployer Manager.
   */
  public function __construct(StaticBuilderPluginManagerInterface $staticBuilderPluginManager, StaticDeployerPluginManagerInterface $staticDeployerPluginManager) {
    $this->staticBuilderPluginManager = $staticBuilderPluginManager;
    $this->staticDeployerPluginManager = $staticDeployerPluginManager;
  }

  /**
   * {@inheritdoc}
   */
  public function alterRoutes(RouteCollection $collection) {
    $localBuilderDefinitions = $this->staticBuilderPluginManager->getLocalDefinitions();
    $deployerDefinitions = $this->staticDeployerPluginManager->getDefinitions();
    if (is_array($localBuilderDefinitions) && count($localBuilderDefinitions) > 0 && is_array($deployerDefinitions) && count($deployerDefinitions) > 0) {
      foreach ($deployerDefinitions as $deployerDefinition) {
        // Add a default route for the first builder.
        $route = new Route(
          '/admin/reports/static/deploy/' . $deployerDefinition['id'] . '/live/releases/list',
          [
            '_controller' => '\Drupal\static_deploy\Controller\ReleaseController::listReleases',
            '_title' => 'Static Deploy - Releases deployed to ' . $deployerDefinition['label'],
            'deployerId' => $deployerDefinition['id'],
            'builderId' => array_keys($localBuilderDefinitions)[0],
          ],
          ['_permission' => 'access site reports']
        );
        $collection->add('static_deployer_' . $deployerDefinition['id'] . '.release_list.live.default', $route);

        // Add another route for all builders.
        $route = new Route(
          '/admin/reports/static/deploy/' . $deployerDefinition['id'] . '/live/releases/list/{builderId}',
          [
            '_controller' => '\Drupal\static_deploy\Controller\ReleaseController::listReleases',
            '_title' => 'Static Deploy - Releases deployed to ' . $deployerDefinition['label'],
            'deployerId' => $deployerDefinition['id'],
          ],
          ['_permission' => 'access site reports']
        );
        $collection->add('static_deployer_' . $deployerDefinition['id'] . '.release_list.live', $route);
      }
    }
  }

}
