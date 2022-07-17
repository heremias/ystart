<?php

namespace Drupal\static_export_exporter_redirect;

/**
 * An interface for redirect providers.
 */
interface RedirectProviderInterface {

  /**
   * Get all redirection rules defined by Redirect module.
   *
   * @return array
   *   An array of redirection rules.
   */
  public function getAllRules(): array;

}
