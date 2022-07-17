<?php

namespace Drupal\static_export_data_resolver_json_serializer\Plugin\static_export\Data\Resolver;

use Drupal\Core\Entity\EntityInterface;
use Drupal\static_export\Exporter\Data\Resolver\DataResolverPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * A resolver that uses serialize() methods.
 *
 * @StaticDataResolver(
 *  id = "json_serializer",
 *  label = "JSON Serializer",
 *  description = "JSON Serializer data resolver",
 *  format = "json"
 * )
 */
class JsonSerializerDataResolver extends DataResolverPluginBase {

  /**
   * Serializer service.
   *
   * @var \Symfony\Component\Serializer\SerializerInterface
   */
  protected $serializer;

  /**
   * Constructor for exporter.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Symfony\Component\Serializer\SerializerInterface $serializer
   *   Serializer service.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    $plugin_definition,
    SerializerInterface $serializer
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->serializer = $serializer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get("serializer")
    );
  }

  /**
   * {@inheritdoc}
   */
  public function resolve(EntityInterface $entity, string $variant = NULL, string $langcode = NULL): string {
    return $this->serializer->serialize($entity, 'json', ['plugin_id' => 'entity']);
  }

}
