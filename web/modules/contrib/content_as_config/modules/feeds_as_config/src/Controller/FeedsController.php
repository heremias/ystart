<?php

namespace Drupal\feeds_as_config\Controller;

use Drupal\content_as_config\Controller\EntityControllerBase;

/**
 * Controller for syncing feeds.
 */
class FeedsController extends EntityControllerBase {

  /**
   * {@inheritdoc}
   */
  public static function entityTypeName(): string {
    return 'feeds_feed';
  }

  /**
   * {@inheritdoc}
   */
  public static function fieldNames(): array {
    return [
      'title',
      'source',
      'type',
    ];
  }

}
