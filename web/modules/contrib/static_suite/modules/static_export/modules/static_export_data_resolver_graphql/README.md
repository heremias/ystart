# Static Export GraphQL Data Resolver

Provides a GraphQL data resolver for Static Export module.

## INTRODUCTION ##
This module is one of the multiple data resolvers available for Static Export.
You should install and configure Static Export before using this module.

Static Export exports your entities to plain files. Data for those entities can
be gathered from different sources, like JSON:API or GraphQL. This module
obtains data from a GraphQL endpoint, executing a query defined per each entity
type and bundle.

## REQUIREMENTS ##
* Static Export module
* GraphQL module

## INSTALLATION ##
Run `composer require drupal/static_export_data_resolver_graphql`.

## CONFIGURATION ##
Configuration available at /admin/config/static/export/entity/resolver/graphql.
