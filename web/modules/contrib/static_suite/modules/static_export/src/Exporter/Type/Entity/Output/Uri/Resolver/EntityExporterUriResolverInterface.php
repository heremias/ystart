<?php

namespace Drupal\static_export\Exporter\Type\Entity\Output\Uri\Resolver;

use Drupal\Core\Entity\EntityInterface;
use Drupal\static_export\Exporter\Output\Uri\Resolver\ExporterUriResolverInterface;

/**
 * Interface for a path resolver of exported entities and custom paths.
 */
interface EntityExporterUriResolverInterface extends ExporterUriResolverInterface {

  /**
   * Set entity to work with.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Entity to work with.
   *
   * @return EntityExporterUriResolverInterface
   *   Return $this so this method can be chainable
   */
  public function setEntity(EntityInterface $entity): EntityExporterUriResolverInterface;

}
