<?php

namespace Drupal\static_export_exporter_redirect;

/**
 * An interface for redirect repositories.
 */
interface RedirectRepositoryInterface {

  /**
   * Finds all available redirects.
   *
   * @return array
   *   Array of redirects
   */
  public function findAll(): array;

}
