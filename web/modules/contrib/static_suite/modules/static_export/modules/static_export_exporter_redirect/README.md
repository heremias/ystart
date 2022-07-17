# Redirect rules exporter for Static Export

Provides a custom exporter that exports redirect rules from Drupal
[Redirect module](https://www.drupal.org/project/redirect) when
 - a URL redirect is added, updated or deleted
 - a path alias is added, updated or deleted
 - a configuration that affects any language changes

Redirects can be stored a format supported by Static Export (JSON, XML, etc) or CSV.

When using CSV, each redirect rule is listed on a separate line. The contents of that line are configurable by using a
set of predefined tokens.

Here is an example:

```
/news           /blog                                           301
/module-page    https://www.drupal.org/project/static_suite     302
```

## INSTALLATION ##
Run `composer require drupal/static_export_exporter_redirect`.

