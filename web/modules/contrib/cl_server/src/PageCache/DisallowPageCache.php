<?php

namespace Drupal\cl_server\PageCache;

use Drupal\cl_server\Util;
use Drupal\Core\PageCache\RequestPolicyInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Do not serve a page from cache if serving from the rendering controller.
 *
 * @internal
 */
class DisallowPageCache implements RequestPolicyInterface {

  /**
   * {@inheritdoc}
   */
  public function check(Request $request) {
    return Util::isRenderController($request) ? static::DENY : NULL;
  }

}
