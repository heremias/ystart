<?php

namespace Drupal\decoupled_pages_test;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\decoupled_pages\DataProviderInterface;
use Drupal\decoupled_pages\Dataset;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Route;

/**
 * Example of dynamic data provider.
 */
final class DataProvider implements DataProviderInterface {

  /**
   * A query parameter name used to dynamically set an HTML data attribute.
   */
  const QUERY_PARAMETER_NAME = 'dynamic_value';

  /**
   * The cache context(s) for the dynamic data.
   *
   * @var string[]
   */
  protected static $cacheContexts = ['url.query_args:dynamic_value'];

  /**
   * {@inheritdoc}
   */
  public function getData(Route $route, Request $request): Dataset {
    $cacheability = new CacheableMetadata();
    $cacheability->addCacheContexts(static::$cacheContexts);
    return Dataset::cacheVariable($cacheability, $request->query->has(static::QUERY_PARAMETER_NAME)
      ? ['dynamic' => $request->query->get(static::QUERY_PARAMETER_NAME)]
      : []
    );
  }

}
