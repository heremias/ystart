<?php

namespace Drupal\static_preview_gatsby_instant\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Listens to the dynamic route events.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  public function alterRoutes(RouteCollection $collection) {
    if ($route = $collection->get('entity.node.preview')) {
      $routeDefaults = $route->getDefaults();
      $routeDefaults['_controller'] = '\Drupal\static_preview_gatsby_instant\Controller\NodePreviewController::view';
      $route->setDefaults($routeDefaults);
    }
  }

}
