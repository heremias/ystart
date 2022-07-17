<?php

namespace Drupal\decoupled_pages\Routing;

use Drupal\Core\Routing\EnhancerInterface;
use Drupal\decoupled_pages\DataProviderInterface;
use Drupal\decoupled_pages\Dataset;
use Drupal\decoupled_pages\Exception\DataProviderException;
use Drupal\decoupled_pages\RouteDefinitionDataProvider;
use Symfony\Cmf\Component\Routing\RouteObjectInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Route;

/**
 * Class DatasetRouteEnhancer.
 *
 * @internal
 */
class DatasetRouteEnhancer implements EnhancerInterface {

  /**
   * A dictionary of dataset providers.
   *
   * @var array[string]\Drupal\decoupled_pages|DatasetProviderInterface
   */
  protected $providers = [];

  /**
   * Adds a service to the list of registered data providers.
   *
   * @param \Drupal\decoupled_pages\DataProviderInterface $provider
   *   A dynamic data attribute provider.
   */
  public function addProvider(DataProviderInterface $provider) {
    assert(isset($provider->_serviceId));
    $this->providers[$provider->_serviceId] = $provider;
  }

  /**
   * {@inheritdoc}
   */
  public function enhance(array $defaults, Request $request) {
    $route = $defaults[RouteObjectInterface::ROUTE_OBJECT];
    assert($route instanceof Route);

    $provider_id = $route->getDefault(RoutingEventSubscriber::DECOUPLED_PAGE_DATA_PROVIDER_ROUTE_DEFAULT_KEY);
    if (!$provider_id) {
      return $defaults;
    }

    $enhanced_data = $this->providers[$provider_id]->getData($route, $request);

    if ($provider_id !== RouteDefinitionDataProvider::SERVICE_ID) {
      foreach ($enhanced_data as $data_name => $_) {
        // The data name must only contain lower-case letters and dashes and may
        // not begin or end with a dash.
        if (!preg_match('/^[a-z\-]+$/', $data_name) || substr($data_name, 0, 1) === '-' || substr($data_name, -1) === '-') {
          $format = 'Data attribute name `%s` is invalid. The data attribute names provided by %s::getData() must only contain lower-case letters and dashes and must not begin or end with a dash.';
          throw new DataProviderException(sprintf($format, $data_name, get_class($this->providers[$provider_id])));
        }
      }
      $route_definition_data_provider = $this->providers[RouteDefinitionDataProvider::SERVICE_ID];
      $route_definition_data = $route_definition_data_provider->getData($route, $request);
      $enhanced_data = Dataset::merge($route_definition_data, $enhanced_data);
    }

    if (isset($defaults[RoutingEventSubscriber::DECOUPLED_PAGE_DATA_ARGUMENT_NAME])) {
      $existing_data = $defaults[RoutingEventSubscriber::DECOUPLED_PAGE_DATA_ARGUMENT_NAME];
      $enhanced_data = Dataset::merge($existing_data, $enhanced_data);
    }

    $defaults[RoutingEventSubscriber::DECOUPLED_PAGE_DATA_ARGUMENT_NAME] = $enhanced_data;

    return $defaults;
  }

}
