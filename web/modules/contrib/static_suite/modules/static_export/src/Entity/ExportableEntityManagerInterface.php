<?php

namespace Drupal\static_export\Entity;

use Drupal\Core\Entity\EntityInterface;

/**
 * An interface for a manager of Exportable Entities.
 */
interface ExportableEntityManagerInterface {

  /**
   * Get all defined exportable entities.
   *
   * @return ExportableEntityInterface[]
   *   Array of ExportableEntityInterface.
   */
  public function getAllExportableEntities(): array;

  /**
   * Get exportable entities that are statified pages.
   *
   * @return ExportableEntityInterface[]
   *   Array of ExportableEntityInterface.
   */
  public function getExportableEntitiesThatAreStatifiedPages(): array;

  /**
   * Given an entity, get its ExportableEntity with its export config.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Entity.
   *
   * @return \Drupal\static_export\Entity\ExportableEntityInterface|null
   *   The ExportableEntity for the $entity param
   */
  public function getExportableEntity(EntityInterface $entity): ?ExportableEntityInterface;

  /**
   * Tells whether an entity is exportable (exists and it's enabled).
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Entity to be checked.
   *
   * @return bool
   *   True if exportable, false otherwise.
   */
  public function isExportable(EntityInterface $entity): bool;

}
