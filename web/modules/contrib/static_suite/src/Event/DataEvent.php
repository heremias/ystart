<?php

namespace Drupal\static_suite\Event;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * Event for altering or reading generic data.
 */
class DataEvent extends Event {

  /**
   * An array with event's data.
   *
   * @var array
   */
  protected $data = [];

  /**
   * Constructs the object.
   *
   * @param array $data
   *   Event data.
   */
  public function __construct(array $data) {
    $this->setData($data);
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

  /**
   * Set event data.
   *
   * @param array $data
   *   Event data.
   */
  public function setData(array $data): void {
    $this->data = $data;
  }

}
