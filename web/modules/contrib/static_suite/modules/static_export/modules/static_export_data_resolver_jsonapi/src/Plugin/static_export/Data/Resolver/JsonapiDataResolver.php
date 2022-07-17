<?php

namespace Drupal\static_export_data_resolver_jsonapi\Plugin\static_export\Data\Resolver;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\static_export\Exporter\Data\Resolver\DataResolverPluginBase;
use Drupal\static_export_data_resolver_jsonapi\JsonapiRequestHandlerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A JSON:API data resolver.
 *
 * @StaticDataResolver(
 *  id = "jsonapi",
 *  label = "JSON:API",
 *  description = "JSON:API data resolver",
 *  format = "json"
 * )
 */
class JsonapiDataResolver extends DataResolverPluginBase {

  /**
   * Config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Handler for requesting data from JSON:API.
   *
   * @var \Drupal\static_export_data_resolver_jsonapi\JsonapiRequestHandlerInterface
   */
  protected $jsonApiRequestHandler;

  /**
   * Constructor for exporter.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Config factory.
   * @param \Drupal\static_export_data_resolver_jsonapi\JsonapiRequestHandlerInterface $jsonApiRequestHandler
   *   Handler for requesting data from JSON:API.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    $plugin_definition,
    ConfigFactoryInterface $config_factory,
    JsonapiRequestHandlerInterface $jsonApiRequestHandler
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->configFactory = $config_factory;
    $this->jsonApiRequestHandler = $jsonApiRequestHandler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory'),
      $container->get('static_export_data_resolver_jsonapi.jsonapi_request_handler')
    );
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   */
  public function resolve(EntityInterface $entity, string $variant = NULL, string $langcode = NULL): string {
    $path = $this->configFactory->get('static_export_data_resolver_jsonapi.settings')
      ->get('endpoint') . '/' . $entity->getEntityTypeId() . '/' . $entity->bundle() . '/' . $entity->uuid();
    $queryParams = [];
    $configQueryParams = $this->configFactory->get('static_export_data_resolver_jsonapi.settings')
      ->get('query_params.' . $entity->getEntityTypeId() . '.' . $entity->bundle());
    if ($configQueryParams) {
      $queryParams = explode('&', $configQueryParams);
    }
    return $this->jsonApiRequestHandler->request($path, $queryParams);
  }

}
