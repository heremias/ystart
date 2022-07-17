# Language metadata exporter for Static Export

Provides a custom exporter that exports language metadata (available languages, prefixes, etc) about current site. That
metadata is useful when building a site, to dynamically know which language is the default one, their prefixes, etc.

It exports its data when any of the following configuration objects changes:
- language.negotiation
- system.site
- language.type
- language.entity.*

## INSTALLATION ##
Run `composer require drupal/static_export_exporter_language_metadata`.

