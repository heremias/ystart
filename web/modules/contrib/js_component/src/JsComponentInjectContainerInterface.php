<?php

namespace Drupal\js_component;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Define the JS component form inject container interface.
 */
interface JsComponentInjectContainerInterface {

  /**
   * Create the form component handler instance.
   *
   * @param array $configuration
   *   An array of component configurations.
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The dependency service container.
   *
   * @return mixed
   */
  public static function create(array $configuration, ContainerInterface $container);
}
