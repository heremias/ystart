<?php

namespace Drupal\decoupled_pages\Controller;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\decoupled_pages\Routing\RoutingEventSubscriber;
use Symfony\Component\HttpFoundation\Request;

/**
 * Serves the standard, empty page for an SPA to attach onto.
 *
 * @internal
 */
class DecoupledPage extends ControllerBase {

  /**
   * Default controller method for a decoupled page.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   A Symfony request object.
   *
   * @return array
   *   A render array.
   */
  public function serve(Request $request): array {
    $libraries = $request->attributes->get(RoutingEventSubscriber::DECOUPLED_PAGE_LIBRARIES_ARGUMENT_NAME);
    $dataset = $request->attributes->get(RoutingEventSubscriber::DECOUPLED_PAGE_DATA_ARGUMENT_NAME);
    $root_element = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => ['id' => 'decoupled-page-root'],
    ];
    foreach ($dataset as $data_name => $data_value) {
      $root_element['#attributes']["data-$data_name"] = $data_value;
    }
    $build = [
      '#type' => 'page',
      'content' => $root_element,
      '#attached' => [
        'library' => $libraries,
      ],
    ];
    CacheableMetadata::createFromObject($dataset)->applyTo($build);
    return $build;
  }

}
