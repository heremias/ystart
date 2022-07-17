<?php

namespace Drupal\static_export\Exporter\Data\Resolver;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;

/**
 * Abstract base class for all data resolvers.
 */
abstract class DataResolverPluginBase extends PluginBase implements DataResolverPluginInterface, ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   *
   * By default, don't support variants. If a data resolver uses them, it should
   * override this method.
   */
  public function getVariantKeys(EntityInterface $entity): array {
    return [];
  }

}
