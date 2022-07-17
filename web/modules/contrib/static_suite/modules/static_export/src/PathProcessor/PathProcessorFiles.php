<?php

namespace Drupal\static_export\PathProcessor;

use Drupal\Core\PathProcessor\InboundPathProcessorInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Defines a path processor to rewrite file URLs.
 *
 * As the route system does not allow arbitrary amount of parameters convert
 * the file path to a query parameter on the request.
 *
 * @see \Drupal\system\PathProcessor\PathProcessorFiles
 */
class PathProcessorFiles implements InboundPathProcessorInterface {

  /**
   * {@inheritdoc}
   */
  public function processInbound($path, Request $request) {
    if (strpos($path, '/static/export/files/') === 0 && !$request->query->has('uri_target')) {
      $uriTarget = preg_replace('|^/static/export/files/|', '', $path);
      $request->query->set('uri_target', $uriTarget);
      return '/static/export/files';
    }
    return $path;
  }

}
