<?php

namespace Drupal\static_export\Plugin\static_export\Output\Uri\Resolver\PagePath;

use Drupal\Component\Plugin\PluginBase;
use Drupal\static_export\Exporter\Output\Uri\Resolver\PagePath\PagePathUriResolverPluginInterface;
use Drupal\static_export\Exporter\Output\Uri\UriInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a page path URI resolver for entity exporters.
 *
 * @StaticPagePathUriResolver (
 *  id = "entity",
 *  label = "Entity page path URI resolver",
 *  description = "Default entity page path URI resolver provided by Static
 *   Export", type = "entity", weight = 0,
 * )
 */
class EntityPagePathUriResolver extends PluginBase implements PagePathUriResolverPluginInterface {

  /**
   * Entity utils from Static Suite.
   *
   * @var \Drupal\static_suite\Entity\EntityUtils
   */
  protected $entityUtils;

  /**
   * The entity exporter URI resolver.
   *
   * @var \Drupal\static_export\Exporter\Type\Entity\Output\Uri\Resolver\EntityExporterUriResolver
   */
  protected $entityExporterUriResolver;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->entityUtils = $container->get("static_suite.entity_utils");
    $instance->entityExporterUriResolver = $container->get("static_export.entity_exporter_uri_resolver");
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function resolve(string $pagePath, string $langcode): ?UriInterface {
    $entityData = $this->entityUtils->getEntityDataByPagePath($pagePath, $langcode);
    if ($entityData) {
      $entity = $this->entityUtils->loadEntity($entityData['entityTypeId'], $entityData['entityId'], $langcode);
      if ($entity) {
        return $this->entityExporterUriResolver->setEntity($entity)
          ->getMainUri();
      }
    }
    return NULL;
  }

}
