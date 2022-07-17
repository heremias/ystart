<?php

namespace Drupal\cl_server\Asset;

use Drupal\cl_server\Util;
use Drupal\Core\Asset\AttachedAssetsInterface;
use Drupal\Core\Http\RequestStack;

class UnoptimizedAssetResolver implements \Drupal\Core\Asset\AssetResolverInterface {

  /**
   * The decorated resolver.
   *
   * @var \Drupal\Core\Asset\AssetResolverInterface
   */
  private $resolver;

  /**
   * Weather or not to skip optimization.
   *
   * @var bool
   */
  private bool $skipOptimization = FALSE;

  /**
   * Creates a new asset resolver.
   *
   * @param \Drupal\Core\Asset\AssetResolverInterface $resolver
   *   The actual resolver.
   */
  public function __construct(\Drupal\Core\Asset\AssetResolverInterface $resolver, RequestStack $request_stack) {
    $this->resolver = $resolver;
    $request = $request_stack->getCurrentRequest();
    $this->skipOptimization = $request && Util::isRenderController($request);
  }

  /**
   * @inheritDoc
   */
  public function getCssAssets(AttachedAssetsInterface $assets, $optimize) {
    return $this->resolver->getCssAssets(
      $assets,
      $this->skipOptimization ? FALSE : $optimize
    );
  }

  /**
   * @inheritDoc
   */
  public function getJsAssets(AttachedAssetsInterface $assets, $optimize) {
    return $this->resolver->getJsAssets(
      $assets,
      $this->skipOptimization ? FALSE : $optimize
    );
  }

}
