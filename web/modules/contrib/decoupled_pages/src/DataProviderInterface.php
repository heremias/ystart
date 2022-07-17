<?php

namespace Drupal\decoupled_pages;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Route;

/**
 * Interface for dynamic data attribute providers.
 */
interface DataProviderInterface {

  /**
   * Gets dynamic data attributes for a decoupled page root element.
   *
   * @param \Symfony\Component\Routing\Route $route
   *   The route definition matched for the given request.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Drupal\decoupled_pages\Dataset
   *   A dataset.
   */
  public function getData(Route $route, Request $request): Dataset;

}
