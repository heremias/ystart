<?php

namespace Drupal\static_export_data_resolver_jsonapi;

/**
 * An interface to define a handler for requesting JSON:API.
 */
interface JsonapiRequestHandlerInterface {

  /**
   * Request a JSON:API endpoint.
   *
   * @param string $path
   *   JSON:API path.
   * @param array $queryParams
   *   Optional array of query params, e.g.- ['include' => 'field_image'].
   *
   * @return string
   *   The request result.
   *
   * @throws \Exception
   */
  public function request(string $path, array $queryParams = []): string;

}
