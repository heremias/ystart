<?php

namespace Drupal\static_export_data_resolver_graphql\Plugin\static_export\Data\Resolver;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\static_export\Exporter\Data\Resolver\DataResolverPluginBase;
use Drupal\static_export\Exporter\ExporterPluginInterface;
use Drupal\static_export_data_resolver_graphql\GraphqlQueryHandlerInterface;
use Drupal\static_suite\Entity\EntityUtils;
use Exception;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A GraphQL data resolver.
 *
 * @StaticDataResolver(
 *  id = "graphql",
 *  label = "GraphQL",
 *  description = "GraphQL data resolver",
 * )
 */
class GraphqlDataResolver extends DataResolverPluginBase {

  /**
   * Config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Entity Utils.
   *
   * @var \Drupal\static_suite\Entity\EntityUtils
   */
  protected $entityUtils;

  /**
   * Handler for querying GraphQL.
   *
   * @var \Drupal\static_export_data_resolver_graphql\GraphqlQueryHandlerInterface
   */
  protected $graphqlQueryHandler;

  /**
   * Constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Config factory.
   * @param \Drupal\static_suite\Entity\EntityUtils $entityUtils
   *   Entity utils service.
   * @param \Drupal\static_export_data_resolver_graphql\GraphqlQueryHandlerInterface $graphqlQueryHandler
   *   Service for querying GraphQL.
   */
  public function __construct(array $configuration,
                              string $plugin_id,
                              $plugin_definition,
                              ConfigFactoryInterface $config_factory,
                              EntityUtils $entityUtils,
                              GraphqlQueryHandlerInterface $graphqlQueryHandler) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->configFactory = $config_factory;
    $this->entityUtils = $entityUtils;
    $this->graphqlQueryHandler = $graphqlQueryHandler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get("config.factory"),
      $container->get("static_suite.entity_utils"),
      $container->get("static_export_data_resolver_graphql.graphql_query_handler")
    );
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\static_suite\StaticSuiteUserException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function resolve(EntityInterface $entity, string $variant = NULL, string $langcode = NULL): array {
    $query = $this->graphqlQueryHandler->getQueryFileContents($entity, $variant);
    $variables = $this->graphqlQueryHandler->getQueryVariables($entity, $langcode);
    return $this->graphqlQueryHandler->query($query, $variables);
  }

  /**
   * {@inheritdoc}
   */
  public function getVariantKeys(EntityInterface $entity): array {
    $variantKeys = [];
    try {
      /** @var \Drupal\static_export\Entity\ExportableEntity $exportableEntity */
      $exportableEntity = $this->entityUtils->loadEntity('exportable_entity', $entity->getEntityTypeId() . '.' . $entity->bundle());
    }
    catch (Exception $e) {
      return $variantKeys;
    }
    if ($exportableEntity && $exportableEntity->status()) {
      $basePath = $this->configFactory->get('static_export_data_resolver_graphql.settings')
        ->get('dir') . '/' . $exportableEntity->getEntityTypeIdString() . '/' . $exportableEntity->id() . ExporterPluginInterface::VARIANT_SEPARATOR;
      $pattern = $basePath . '*.gql';
      $files = glob($pattern);
      foreach ($files as $file) {
        $variantFilename = str_replace($basePath, '', $file);
        $variantKey = pathinfo($variantFilename, PATHINFO_FILENAME);
        $variantKeys[] = $variantKey;
      }
    }
    return $variantKeys;
  }

}
