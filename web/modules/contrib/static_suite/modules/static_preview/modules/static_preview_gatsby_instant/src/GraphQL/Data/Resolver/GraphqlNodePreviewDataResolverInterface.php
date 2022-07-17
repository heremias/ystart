<?php

namespace Drupal\static_preview_gatsby_instant\GraphQL\Data\Resolver;

use Drupal\node\NodeInterface;

/**
 * Defines an interface for the GraphQL node preview resolver.
 */
interface GraphqlNodePreviewDataResolverInterface {

  /**
   * Get the preview data from a node, using "NodePreviewByUuid" query.
   *
   * "NodePreviewByUuid" query is provided by "graphql_node_preview" module.
   * That query works only with nodes, hence this method allows only nodes
   * instead of entities.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node to get preview data for.
   *
   * @return array
   *   Node's preview data.
   */
  public function resolve(NodeInterface $node): array;

}
