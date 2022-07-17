<?php

namespace Drupal\static_export_data_resolver_jsonapi;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * A handler for requesting JSON:API data.
 */
class JsonapiRequestHandler implements JsonapiRequestHandlerInterface {

  /**
   * Symfony\Component\HttpKernel\HttpKernelInterface definition.
   *
   * @var \Symfony\Component\HttpKernel\HttpKernelInterface
   */
  protected $httpKernel;

  /**
   * Constructor.
   *
   * @param \Symfony\Component\HttpKernel\HttpKernelInterface $http_kernel
   *   Http kernel service.
   */
  public function __construct(HttpKernelInterface $http_kernel) {
    $this->httpKernel = $http_kernel;
  }

  /**
   * {@inheritdoc}
   */
  public function request(string $path, array $queryParams = []): string {
    $subRequest = Request::create($path . (count($queryParams) ? '?' . implode('&', $queryParams) : ''), 'GET');
    $response = $this->httpKernel->handle($subRequest, HttpKernelInterface::SUB_REQUEST);
    return $response->getContent();
  }

}
