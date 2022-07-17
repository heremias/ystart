<?php

namespace Drupal\decoupled_pages;

use Drupal\decoupled_pages\Routing\RoutingEventSubscriber;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Route;

/**
 * Class RouteDefinitionDataProvider.
 *
 * @internal
 */
final class RouteDefinitionDataProvider implements DataProviderInterface {

  const SERVICE_ID = 'decoupled_pages.route_definition_data_provider';

  /**
   * {@inheritdoc}
   */
  public function getData(Route $route, Request $request): Dataset {
    $route_definition_dataset = $route->getDefault(RoutingEventSubscriber::DECOUPLED_PAGE_DATA_ROUTE_DEFAULT_KEY) ?? [];
    return Dataset::cachePermanent($route_definition_dataset);
  }

}
