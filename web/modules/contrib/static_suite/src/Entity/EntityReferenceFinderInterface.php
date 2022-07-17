<?php

namespace Drupal\static_suite\Entity;

use Drupal\Core\Entity\EntityInterface;

/**
 * Interface to define a finder of entity references.
 *
 * The problem with this dependencies approach is:
 *  - Slow export time when an entity is referenced in lots of places
 *  - Unpublished entities are not exported on a dependency operation, and
 *    sometimes it could be useful to do that, for example when previewing a set
 *    of related pages
 *  - When a dependant entity is exported, it doesn't trigger any of its related
 *    events, which could be needed sometimes.
 *
 * Therefore, we are moving to a "includes" approach.
 */
interface EntityReferenceFinderInterface {

  /**
   * Find references to an entity in nodes or paragraphs.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Entity to be found.
   * @param int $maxRecursionLevel
   *   Optional, defaults to 1. 0 for infinite recursion.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   *   Empty array or ids of the related nodes.
   */
  public function findReferences(EntityInterface $entity, int $maxRecursionLevel = 1): array;

}
