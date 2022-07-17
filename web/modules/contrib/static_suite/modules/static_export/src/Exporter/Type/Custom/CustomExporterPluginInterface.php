<?php

namespace Drupal\static_export\Exporter\Type\Custom;

use Drupal\static_export\Exporter\ExporterPluginInterface;

/**
 * Defines an interface for custom exporters.
 */
interface CustomExporterPluginInterface extends ExporterPluginInterface {

  // @todo - define methods so exporter options are typed.

  /**
   * Get page paths supported by a custom exporter.
   *
   * There are some cases where a data file exported by a custom exporter is
   * used to view a page that is not present in Drupal. This is the case
   * when:
   * 1) a page is manually created at build time by a SSG, without any Drupal
   *    counterpart; and
   * 2) a preview module, like static_preview_gatsby_instant, needs to know
   *    where is the exported file stored.
   *
   * For such cases, we should define the relationship between paths (URIs or
   * aliases) and data files.
   *
   * Avoid manually setting that relationship directly inside the exporter's
   * code, because that would make it non-reusable for other projects where
   * supported paths change. Create a configuration form for the exporter and
   * define those supported paths there.
   *
   * @param string $langcode
   *   Language code to look up the supported paths.
   *
   * @return array
   *   Array where the key is a Regular Expression to define supported paths
   *   (with a leading slash) and value is a replacement for the previous Regex
   *   pointing to a URI target (without scheme) inside Static Export's data
   *   directory. E.g.- ['^\/my-custom-page' ---
   *   custom/pages/my-custom-page.json] The array value (the replacement) must
   *   be a plain string and not an UriInterface object, to be able to use
   *   references ($n) from the key.
   */
  public function getSupportedPagePaths(string $langcode): array;

}
