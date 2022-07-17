<?php

namespace Drupal\static_export_data_resolver_graphql;

use Drupal\Core\Entity\EntityInterface;

/**
 * An interface to define a handler for querying GraphQL.
 */
interface GraphqlQueryHandlerInterface {

  /**
   * Executes a GraphQL query.
   *
   * @param string $graphqlQuery
   *   GraphQL query.
   * @param array $variables
   *   Optional array of variables to pass to the GraphQL query. It can be an
   *   array of items using using the format defined in
   *   GraphqlQueryHandlerInterface::getQueryVariables ("value" and "type") or
   *   a simple array with a key and a value.
   *
   * @return array
   *   The query result.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\static_suite\StaticSuiteUserException
   */
  public function query(string $graphqlQuery, array $variables = []): array;

  /**
   * Get the contents of a GraphQL query file for an entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity for which the GraphQL file contents are obtained.
   * @param string|null $variant
   *   Variant key, optional.
   *
   * @return string|null
   *   The contents of a GraphQL query file.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\static_suite\StaticSuiteUserException
   */
  public function getQueryFileContents(EntityInterface $entity, string $variant = NULL): ?string;

  /**
   * Get an array of possible variables to be used in a GraphQL query.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity for which the GraphQL query variables are obtained.
   * @param string|null $langcode
   *   Optional language.
   *
   * @return array
   *   An array with possible variables to be used in a GraphQL query.
   *   Each array element contains two keys, eyed by the variable name:
   *   1) value: value of the variable
   *   2) type: GraphQL type of the variable (String, LanguageId,
   *   LanguageIdAll, etc).
   */
  public function getQueryVariables(EntityInterface $entity, string $langcode = NULL): array;

}
