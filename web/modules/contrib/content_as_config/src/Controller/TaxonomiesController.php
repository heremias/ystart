<?php

namespace Drupal\content_as_config\Controller;

/**
 * Controller for syncing taxonomy terms.
 */
class TaxonomiesController extends EntityControllerBase {

  /**
   * {@inheritdoc}
   */
  public static function entityTypeName(): string {
    return 'taxonomy_term';
  }

  /**
   * {@inheritdoc}
   */
  public static function fieldNames(): array {
    return [
      'tid',
      'vid',
      'name',
      'langcode',
      'description',
      'weight',
      'parent',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function hasPath(): bool {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  protected function getExportableEntities(?array $export_list): array {
    $entities = [];
    $storage = $this->entityTypeManager->getStorage('taxonomy_term');
    if (isset($export_list)) {
      $export_list = array_filter($export_list, 'is_string');
      if (!empty($export_list)) {
        $entities = $storage->loadByProperties(['vid' => $export_list]);
      }
    }
    else {
      $entities = $storage->loadMultiple();
    }
    return $entities;
  }

}
