<?php

namespace Drupal\js_component;

/**
 * Define the JS component attachment class.
 */
class JSComponentAttachment implements \IteratorAggregate {

  /**
   * @var array
   */
  protected $attached = [];

  /**
   * Add library attachment.
   *
   * @param $library
   *   The name of the library.
   *
   * @return \Drupal\js_component\JSComponentAttachment
   */
  public function addLibrary($library) {
    $libraries = $this->attached['library'] ?? [];

    if (!in_array($library, $libraries, TRUE)) {
      $this->attached['library'][] = $library;
    }

    return $this;
  }

  /**
   * Add drupalSettings attachment.
   *
   * @param $namespace
   *   The module namespace.
   * @param array $settings
   *   An array of settings.
   *
   * @return \Drupal\js_component\JSComponentAttachment
   */
  public function addDrupalSettings($namespace, array $settings) {
    if (!empty($settings)) {
      $current = $this->attached['drupalSettings'][$namespace] ?? [];

      $this->attached['drupalSettings'][$namespace] = array_merge_recursive(
        $current,
        $settings
      );
    }

    return $this;
  }

  /**
   * {@inheritDoc}
   */
  public function getIterator() {
    return new \ArrayIterator($this->attached);
  }
}
