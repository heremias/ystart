<?php

namespace Drupal\static_preview_gatsby_instant\GraphQL\Data\Resolver;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\node\NodeInterface;
use Drupal\static_export_data_resolver_graphql\GraphqlQueryHandlerInterface;
use Drupal\static_suite\Entity\EntityUtils;

/**
 * A GraphQL resolver.
 */
class GraphqlNodePreviewDataResolver implements GraphqlNodePreviewDataResolverInterface {

  /**
   * Config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Language Manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

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
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Config factory.
   * @param \Drupal\static_suite\Entity\EntityUtils $entityUtils
   *   Entity utils service.
   * @param \Drupal\static_export_data_resolver_graphql\GraphqlQueryHandlerInterface $graphqlQueryHandler
   *   Service for querying GraphQL.
   */
  public function __construct(ConfigFactoryInterface $config_factory, EntityUtils $entityUtils, GraphqlQueryHandlerInterface $graphqlQueryHandler) {
    $this->configFactory = $config_factory;
    $this->entityUtils = $entityUtils;
    $this->graphqlQueryHandler = $graphqlQueryHandler;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\static_suite\StaticSuiteUserException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function resolve(NodeInterface $node): array {
    $query = $this->graphqlQueryHandler->getQueryFileContents($node);
    $langcode = $node->language()->getId();
    $variables = $this->graphqlQueryHandler->getQueryVariables($node, $langcode);

    // Replace nodeById with nodePreviewByUuid.
    $query = preg_replace("/content\s*:\s*nodeById\([^(]+\)/", 'content:nodePreviewByUuid(uuid: "' . $node->uuid() . '")', $query);

    // Support "GraphQL Fragment Include" module, and load fragments at an
    // early stage so we can detect when a variable is used.
    $graphqlFragmentLoaderId = 'graphql_fragment_include.graphql_fragment_loader';
    if (\Drupal::hasService($graphqlFragmentLoaderId)) {
      // phpcs:ignore
      $query = \Drupal::service($graphqlFragmentLoaderId)
        ->loadFragments($query);
    }

    // Remove any use of variables.
    foreach ($variables as $variableKey => $variableData) {
      $variableIsBeingUsed = mb_substr_count($query, '$' . $variableKey) > 1;
      if (!$variableIsBeingUsed) {
        $query = preg_replace('/\s*\$' . $variableKey . '\s*:\s*' . $variableData['type'] . '!?\s*,?\s*/', '', $query);
      }
    }

    // Remove invalid empty parenthesis.
    $query = str_replace(['() {', '(,) {'], ' {', $query);

    return $this->graphqlQueryHandler->query($query, $variables);
  }

}
