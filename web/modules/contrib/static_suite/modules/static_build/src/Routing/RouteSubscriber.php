<?php

namespace Drupal\static_build\Routing;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\static_build\Plugin\StaticBuilderPluginInterface;
use Drupal\static_build\Plugin\StaticBuilderPluginManagerInterface;
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
   * Constructs the subscriber.
   *
   * @param \Drupal\static_build\Plugin\StaticBuilderPluginManagerInterface $staticBuilderPluginManager
   *   Static Builder Manager.
   */
  public function __construct(StaticBuilderPluginManagerInterface $staticBuilderPluginManager) {
    $this->staticBuilderPluginManager = $staticBuilderPluginManager;
  }

  /**
   * {@inheritdoc}
   */
  public function alterRoutes(RouteCollection $collection) {
    // Create routes for builders.
    $runModes = [
      StaticBuilderPluginInterface::RUN_MODE_LIVE,
      StaticBuilderPluginInterface::RUN_MODE_PREVIEW,
    ];
    foreach ($runModes as $runMode) {
      if ($runMode === StaticBuilderPluginInterface::RUN_MODE_LIVE) {
        $builderDefinitions = $this->staticBuilderPluginManager->getDefinitions();
      }
      else {
        $builderDefinitions = $this->staticBuilderPluginManager->getLocalDefinitions();
      }
      if (is_array($builderDefinitions) && count($builderDefinitions) > 0) {
        // Add a default route for the first builder.
        $route = new Route(
          '/admin/reports/static/build/' . $runMode . '/releases/list',
          [
            '_controller' => '\Drupal\static_build\Controller\ReleaseController::listReleases',
            '_title' => 'Static Build - ' . Unicode::ucfirst($runMode) . ' releases',
            'runMode' => $runMode,
            'builderId' => array_keys($builderDefinitions)[0],
          ],
          ['_permission' => 'access site reports']
        );
        $collection->add('static_build.release_list.' . $runMode . '.default', $route);

        // Add another route for all builders.
        $route = new Route(
          '/admin/reports/static/build/' . $runMode . '/releases/list/{builderId}',
          [
            '_controller' => '\Drupal\static_build\Controller\ReleaseController::listReleases',
            '_title' => 'Static Build - ' . Unicode::ucfirst($runMode) . ' releases',
            'runMode' => $runMode,
          ],
          ['_permission' => 'access site reports']
        );
        $collection->add('static_build.release_list.' . $runMode, $route);
      }
    }
  }

}
