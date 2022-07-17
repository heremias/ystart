<?php

namespace Drupal\js_component\Event;

use Drupal\js_component\Plugin\JSComponent;
use Symfony\Component\EventDispatcher\Event;

/**
 * Build component data event object.
 */
class BuildComponentDataEvent extends Event {

  /**
   * @var \Drupal\js_component\Plugin\JSComponent
   */
  protected $component;

  /**
   * @var array
   */
  protected $collection = [];

  /**
   * @var array
   */
  protected $configuration = [];

  /**
   * The constructor for the build component data event.
   *
   * @param array $configuration
   *   An array of the JS component block configuration.
   * @param \Drupal\js_component\Plugin\JSComponent $component
   */
  public function __construct(
    array $configuration,
    JSComponent $component
  ) {
    $this->component = $component;
    $this->configuration = $configuration;
  }

  /**
   * Get the JS component instance.
   *
   * @return \Drupal\js_component\Plugin\JSComponent
   */
  public function getComponent() {
    return $this->component;
  }

  /**
   * Get the configuration array.
   *
   * @return array
   */
  public function getConfiguration() {
    return $this->configuration;
  }

  /**
   * Build the component data array.
   *
   * @return array
   */
  public function build() {
    if (!empty($this->collection)) {
      return array_replace_recursive(
        ...$this->collection
      );
    }

    return [];
  }

  /**
   * Add the component data to the collection.
   *
   * @param array $data
   *   An array of data values.
   *
   * @return self
   */
  public function addComponentData(array $data) {
    $this->collection[] = $data;

    return $this;
  }
}
