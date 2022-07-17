<?php

namespace Drupal\content_as_config\Controller;

/**
 * Controller for syncing feeds.
 */
class BlockContentController extends EntityControllerBase {

  /**
   * {@inheritdoc}
   */
  public static function entityTypeName(): string {
    return 'block_content';
  }

  /**
   * {@inheritdoc}
   */
  public static function fieldNames(): array {
    return [
      'id',
      'info',
      'langcode',
      'type',
      'reusable',
    ];
  }

}
