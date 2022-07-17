<?php

namespace Drupal\js_component;

/**
 * Define the JS component data provider interface.
 */
interface JsComponentDataProviderInterface {

  /**
   * Fetch the component data.
   *
   * @return array
   *   An array of component data to output.
   */
  public function fetch();
}
