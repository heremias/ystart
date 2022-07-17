<?php

namespace Drupal\gatsby\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Defines a class for serving routes for Gatsby fastbuilds.
 */
class GatsbyFastbuildsController extends ControllerBase {

  /**
   * Drupal\Core\Entity\EntityTypeManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Drupal\gatsby\GatsbyEntityLogger definition.
   *
   * @var \Drupal\gatsby\GatsbyEntityLogger
   */
  protected $gatsbyEntityLogger;

  /**
   * Drupal\Core\State\State definition.
   *
   * @var \Drupal\Core\State\State
   */
  protected $state;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->gatsbyEntityLogger = $container->get('gatsby.logger');
    $instance->state = $container->get('state');
    return $instance;
  }

  /**
   * Gatsby Fastbuilds sync callback to get incremental content changes.
   *
   * @return Symfony\Component\HttpFoundation\JsonResponse
   *   Returns a JsonResponse with all of the content changes since last fetch.
   */
  public function sync($last_fetch) {
    $last_logtime = $this->state->get('gatsby.last_logtime', 0);

    $sync_data = [
      'status' => -1,
      'timestamp' => time(),
    ];

    if (!empty($last_fetch) && $last_fetch >= $last_logtime) {
      // Get all of the sync entities.
      $sync_data = $this->gatsbyEntityLogger->getSync($last_fetch);
    }

    // Log a message via the system logger about this request.
    if ($this->config('gatsby.settings')->get('log_json')) {
      $this->getLogger('gatsby')
        ->debug("Request: " . $last_fetch . "\nJSON:\n" . json_encode($sync_data));
    }

    return new JsonResponse($sync_data);
  }

}
