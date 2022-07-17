<?php

namespace Drupal\js_component\Event;

/**
 * Define the JS component events.
 */
final class Events {

  /**
   * The build component data fired when the component data is built.
   *
   * Allows for reacting on the creation of the component data. The
   * \Drupal\js_component\Event\BuildComponentDataEvent is provided.
   *
   * @Event
   */
  public const BUILD_COMPONENT_DATA = 'js_component.build_component_data';
}
