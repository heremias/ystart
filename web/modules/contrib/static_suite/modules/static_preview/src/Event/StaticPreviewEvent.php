<?php

namespace Drupal\static_preview\Event;

use Drupal\Core\Entity\EntityInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Static preview event.
 */
class StaticPreviewEvent extends Event {

  /**
   * A flag to tell whether and entity can be previewed.
   *
   * @var bool
   */
  protected $previewable = TRUE;

  /**
   * The entity.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected $entity;

  /**
   * StaticPreviewEvent constructor.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   */
  public function __construct(EntityInterface $entity) {
    $this->entity = $entity;
  }

  /**
   * Tell if an entity can be previewed.
   *
   * @return bool
   *   True if can be previewed, false otherwise.
   */
  public function isPreviewable(): bool {
    return $this->previewable;
  }

  /**
   * Set if an entity can be previewed.
   *
   * @param bool $flag
   *   A flag to tell whether it can previewed or not.
   */
  public function setPreviewable(bool $flag): void {
    $this->previewable = $flag;
  }

  /**
   * Get the entity being previewed.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The entity.
   */
  public function getEntity(): EntityInterface {
    return $this->entity;
  }

}
