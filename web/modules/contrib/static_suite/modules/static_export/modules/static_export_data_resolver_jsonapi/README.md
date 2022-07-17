# Static Export JSON:API Data Resolver

Provides a JSON:API data resolver for Static Export module.

## INTRODUCTION ##
This module is one of the multiple data resolvers available for Static Export.
You should install and configure Static Export before using this module.

Static Export exports your entities to plain files. Data for those entities can
be gathered from different sources, like JSON:API or GraphQL. This module
obtains data from a JSON:API endpoint, executing a request per each entity
type and bundle.

## REQUIREMENTS ##
* Static Export module
* JSON:API module

## INSTALLATION ##
Run `composer require drupal/static_export_data_resolver_jsonapi`.

## CONFIGURATION ##
Configuration available at /admin/config/static/export/entity/resolver/jsonapi.
