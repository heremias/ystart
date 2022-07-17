<?php

namespace Drupal\static_export\Exporter\Data\Resolver;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Entity\EntityInterface;

/**
 * Defines an interface for Resolver plugins.
 */
interface DataResolverPluginInterface extends PluginInspectionInterface {

  /**
   * Gets the data for an entity, using a specific resolver.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The Entity to export.
   * @param string|null $variant
   *   Variant key, optional.
   * @param string|null $langcode
   *   Optional language code. Only useful if entity does not implement
   *   Drupal\Core\TypedData\TranslatableInterface (i.e.- ConfigEntityInterface)
   *
   * @return array|string
   *   Entity data. An array if resolver supports several output formats, or an
   *   string if not.
   */
  public function resolve(EntityInterface $entity, string $variant = NULL, string $langcode = NULL);

  /**
   * Get variant keys of an entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The Entity to search for variants.
   *
   * @return array
   */
  public function getVariantKeys(EntityInterface $entity): array;

}
