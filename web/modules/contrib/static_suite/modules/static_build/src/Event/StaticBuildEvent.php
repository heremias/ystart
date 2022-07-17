<?php

namespace Drupal\static_build\Event;

use Drupal\static_build\Plugin\StaticBuilderPluginInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Generic static build event to be fired.
 */
class StaticBuildEvent extends Event {

  /**
   * The static builder that triggers the event.
   *
   * @var \Drupal\static_build\plugin\StaticBuilderPluginInterface
   */
  protected $builder;

  /**
   * An array with structured data.
   *
   * @var array
   */
  protected $data;

  /**
   * Constructs the object.
   *
   * @param \Drupal\static_build\Plugin\StaticBuilderPluginInterface $builder
   *   The static builder.
   */
  public function __construct(StaticBuilderPluginInterface $builder) {
    $this->builder = $builder;
  }

  /**
   * Get the static builder.
   *
   * @return \Drupal\static_build\Plugin\StaticBuilderPluginInterface
   *   The static builder.
   */
  public function getBuilder(): StaticBuilderPluginInterface {
    return $this->builder;
  }

  /**
   * Set event data.
   *
   * @param array $data
   *   An array with data.
   */
  public function setData(array $data): void {
    $this->data = $data;
  }

  /**
   * Get event data.
   *
   * @return array
   *   The event data.
   */
  public function getData(): array {
    return $this->data;
  }

}
