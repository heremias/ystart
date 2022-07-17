<?php

namespace Drupal\decoupled_pages\Routing;

use Drupal\Component\Assertion\Inspector;
use Drupal\Core\Asset\LibraryDiscoveryInterface;
use Drupal\Core\Routing\RouteBuildEvent;
use Drupal\Core\Routing\RoutingEvents;
use Drupal\decoupled_pages\Exception\RouteDefinitionException;
use Drupal\decoupled_pages\RouteDefinitionDataProvider;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Routing\Route;

/**
 * Decorates decoupled page route definitions.
 *
 * @internal
 */
final class RoutingEventSubscriber implements EventSubscriberInterface {

  /**
   * The route default key for indicating a decoupled page route.
   */
  const DECOUPLED_PAGE_ROUTE_DEFAULT_KEY = '_decoupled_page_main';

  /**
   * The route option key for adding assets to a decoupled page route.
   */
  const DECOUPLED_PAGE_ASSETS_ROUTE_OPTION_KEY = '_decoupled_page_assets';

  /**
   * The route option key for adding assets to a decoupled page route.
   */
  const DECOUPLED_PAGE_PATHS_ROUTE_OPTION_KEY = '_decoupled_page_paths';

  /**
   * The route default key configuring the data attribute of the root element.
   */
  const DECOUPLED_PAGE_DATA_ROUTE_DEFAULT_KEY = '_decoupled_page_data';

  /**
   * The route default key configuring a data provider.
   */
  const DECOUPLED_PAGE_DATA_PROVIDER_ROUTE_DEFAULT_KEY = '_decoupled_page_data_provider';

  /**
   * The route argument name for the route libraries list.
   *
   * This route default is for internal use only.
   */
  const DECOUPLED_PAGE_LIBRARIES_ARGUMENT_NAME = 'decoupled_page_libraries';

  /**
   * The route argument name for the root element dataset.
   *
   * This route default is for internal use only.
   */
  const DECOUPLED_PAGE_DATA_ARGUMENT_NAME = 'decoupled_page_data';

  /**
   * The route argument name for a custom data provider.
   *
   * This route default is for internal use only.
   */
  const DECOUPLED_PAGE_DATA_PROVIDER_ARGUMENT_NAME = 'decoupled_page_data_provider';

  /**
   * A library discovery service.
   *
   * @var \Drupal\Core\Asset\LibraryDiscoveryInterface
   */
  protected $libraries;

  /**
   * A DI container.
   *
   * @var \Symfony\Component\DependencyInjection\ContainerInterface
   */
  protected $container;

  /**
   * RoutingEventSubscriber constructor.
   *
   * @param \Drupal\Core\Asset\LibraryDiscoveryInterface $libraries
   *   A library discovery service.
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   A container.
   */
  public function __construct(LibraryDiscoveryInterface $libraries, ContainerInterface $container) {
    $this->libraries = $libraries;
    $this->container = $container;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[RoutingEvents::ALTER][] = ['alterRoutes'];
    return $events;
  }

  /**
   * Configure `_spa_main` routes.
   *
   * @param \Drupal\Core\Routing\RouteBuildEvent $event
   *   A route build event.
   */
  public function alterRoutes(RouteBuildEvent $event) {
    $route_collection = $event->getRouteCollection();
    foreach ($route_collection as $route_name => $route) {
      if ($route->getDefault(self::DECOUPLED_PAGE_ROUTE_DEFAULT_KEY) !== NULL) {
        $this->alterDecoupledPageRoute($route_name, $route);
        if ($paths = $route->getOption('_decoupled_page_paths')) {
          foreach ($paths as $key => $path) {
            $decoupled_page_route = new Route(
              $path,
              $route->getDefaults(),
              $route->getRequirements(),
              $route->getOptions(),
              $route->getHost(),
              $route->getSchemes(),
              $route->getMethods(),
              $route->getCondition()
            );
            $route_collection->add("$route_name.$key", $decoupled_page_route);
          }
        }
      }
    }
  }

  /**
   * Validates and configures a decoupled page route definition.
   *
   * @param string $route_name
   *   The route name.
   * @param \Symfony\Component\Routing\Route $route
   *   The route definition object.
   */
  protected function alterDecoupledPageRoute(string $route_name, Route $route) {
    $disallowed_route_defaults = [
      static::DECOUPLED_PAGE_LIBRARIES_ARGUMENT_NAME,
      static::DECOUPLED_PAGE_DATA_ARGUMENT_NAME,
      static::DECOUPLED_PAGE_DATA_PROVIDER_ARGUMENT_NAME,
    ];
    foreach ($disallowed_route_defaults as $disallowed_route_default) {
      if (!is_null($route->getDefault($disallowed_route_default))) {
        $format = 'The %s route default must not be specified on the %s route definition.';
        throw new RouteDefinitionException(sprintf($format, $disallowed_route_default, $route_name));
      }
    }

    // Validate any additional paths.
    if ($paths = $route->getOption(static::DECOUPLED_PAGE_PATHS_ROUTE_OPTION_KEY)) {
      if (!Inspector::assertAllStrings($paths) || !Inspector::assertAllStrings(array_keys($paths))) {
        $format = 'The %s route option, given on the %s route definition, must be a mapping of names to paths.';
        throw new RouteDefinitionException(sprintf($format, static::DECOUPLED_PAGE_PATHS_ROUTE_OPTION_KEY, $route_name));
      }
      foreach ($paths as $path) {
        if (strpos($path, $route->getPath()) !== 0) {
          $format = 'The %s route option, given on the %s route definition, must only contain paths that begin with the same path as the parent route.';
          throw new RouteDefinitionException(sprintf($format, static::DECOUPLED_PAGE_PATHS_ROUTE_OPTION_KEY, $route_name));
        }
      }
    }

    // Validate and combine the main and additional asset libraries from the
    // route definition into a route default to be used by the default
    // controller. First, ensure that the route definition does not already
    // define a value for that route default.
    $main = $route->getDefault(self::DECOUPLED_PAGE_ROUTE_DEFAULT_KEY);
    $this->checkRouteAsset($route_name, $main);
    $assets = $route->getOption(static::DECOUPLED_PAGE_ASSETS_ROUTE_OPTION_KEY) ?? [];
    if (!is_array($assets) || !Inspector::assertAllStrings($assets)) {
      $format = 'The %s route option, given on the %s route definition, must be a sequence of strings.';
      throw new RouteDefinitionException(sprintf($format, static::DECOUPLED_PAGE_ASSETS_ROUTE_OPTION_KEY, $route_name));
    }
    foreach ($assets as $asset) {
      $this->checkRouteAsset($route_name, $asset);
    }
    $route->setDefault(static::DECOUPLED_PAGE_LIBRARIES_ARGUMENT_NAME, array_merge([$main], array_values($assets)));

    // Validate and configure the root element's data attribute.
    $element_dataset = $route->getDefault(static::DECOUPLED_PAGE_DATA_ROUTE_DEFAULT_KEY);
    if (!is_null($element_dataset) && (!is_array($element_dataset) || !Inspector::assertAllStrings(array_keys($element_dataset)) || !Inspector::assertAllStrings($element_dataset))) {
      $format = 'The %s route default must on the %s route definition must be a mapping of strings to strings.';
      throw new RouteDefinitionException(sprintf($format, static::DECOUPLED_PAGE_DATA_ROUTE_DEFAULT_KEY, $route_name));
    }
    if ($element_dataset) {
      foreach (array_keys($element_dataset) as $data_name) {
        // The data name must only contain lower-case letters and dashes and may
        // not begin or end with a dash.
        if (!preg_match('/^[a-z\-]+$/', $data_name) || substr($data_name, 0, 1) === '-' || substr($data_name, -1) === '-') {
          $format = 'The %s route default\'s data attribute names on the %s route definition must only contain lower-case letters and dashes and must not begin or end with a dash.';
          throw new RouteDefinitionException(sprintf($format, static::DECOUPLED_PAGE_DATA_ROUTE_DEFAULT_KEY, $route_name));
        }
      }
    }

    $provider_default = $route->getDefault(static::DECOUPLED_PAGE_DATA_PROVIDER_ROUTE_DEFAULT_KEY);
    if (is_null($provider_default)) {
      $provider_default = RouteDefinitionDataProvider::SERVICE_ID;
    }
    elseif (!is_string($provider_default)) {
      $format = 'The %s route default on the %s route definition must be a string identifying a data provider service.';
      throw new RouteDefinitionException(sprintf($format, static::DECOUPLED_PAGE_DATA_PROVIDER_ROUTE_DEFAULT_KEY, $route_name));
    }
    else {
      if (!$this->container->has($provider_default)) {
        $format = 'The %s route default on the %s route definition must be a string identifying a data provider service. The service %s has not been registered.';
        throw new RouteDefinitionException(sprintf($format, static::DECOUPLED_PAGE_DATA_PROVIDER_ROUTE_DEFAULT_KEY, $route_name, $provider_default));
      }
    }
    $route->setDefault(static::DECOUPLED_PAGE_DATA_PROVIDER_ROUTE_DEFAULT_KEY, $provider_default);

    // If the route does not define a method list, ensure that only `GET` is
    // accepted.
    $methods = $route->getMethods();
    if (empty($methods)) {
      $route->setMethods(['GET']);
    }

    // Decoupled page routes must not define a custom controller.
    if ($route->getDefault('_controller')) {
      $format = 'The %s route definition must not declare a _controller route default when the %s route default is declared.';
      throw new RouteDefinitionException($format, $route_name, static::DECOUPLED_PAGE_ROUTE_DEFAULT_KEY);
    }

    // Set the standard decoupled page controller.
    $route->setDefault('_controller', 'controller.decoupled_pages.main:serve');
  }

  /**
   * Verifies that an asset exists.
   *
   * @param string $route_name
   *   The route name requiring the asset.
   * @param string $asset
   *   The asset ID.
   */
  protected function checkRouteAsset($route_name, $asset) {
    list($extension, $library_name) = explode('/', $asset, 2);
    $library = $this->libraries->getLibraryByName($extension, $library_name);
    if ($library === FALSE) {
      $format = 'The %s library specified by the %s route does not exist.';
      throw new RouteDefinitionException(sprintf($format, $asset, $route_name));
    }
  }

}
