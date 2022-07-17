<?php

namespace Drupal\js_component;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Define the JS component data provider generic class.
 */
abstract class JsComponentDataProviderBase implements JsComponentDataProviderInterface, JsComponentInjectContainerInterface {

  protected $configuration = [];

  /**
   * The JS component data provider base constructor.
   *
   * @param array $configuration
   *   Ann array of data provider configuration.
   */
  public function __construct(array $configuration) {
    $this->configuration = $configuration;
  }

  /**
   * {@inheritDoc}
   */
  public static function create(array $configuration, ContainerInterface $container) {
    return new static($configuration);
  }

  /**
   * Get the data provider configuration.
   *
   * @return array
   *   An array of the data provider configurations.
   */
  public function getConfiguration() {
    return $this->configuration;
  }
}
