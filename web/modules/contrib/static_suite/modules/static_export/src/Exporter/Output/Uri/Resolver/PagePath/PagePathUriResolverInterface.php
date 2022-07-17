<?php

namespace Drupal\static_export\Exporter\Output\Uri\Resolver\PagePath;

use Drupal\static_export\Exporter\Output\Uri\UriInterface;

/**
 * Interface for URI resolvers of page paths.
 *
 * There are four types of exporters:
 * 1) entity
 * 2) config
 * 3) locale
 * 4) custom.
 *
 * Configuration objects and locales don't have a public URL like entities (*).
 * Custom exporters can be used to export a data file that is used to create a
 * public URL. Hence, this resolver only takes entity and custom exporters into
 * account.
 *
 * (*) In fact, config/locale exporter could export a data file with all the
 * fields required to create a public URL, using
 * StaticExportEvents::RESOLVER_END. That could work for configuration objects,
 * because you decide which ones are exported and which ones not; then, some of
 * them can be used during the building process of your site, and others be used
 * to create public URLs. BUT, this is not true for locales, because you cannot
 * decide which ones are exported and which ones not: all of them are exported,
 * so you cannot use them to create your site and, at the same time, create
 * public URLs. Moreover, doing that would go against the idea of
 * using config/locale exporter data as a helper for the build process of a
 * site, and therefore it's considered bad practice.
 *
 * If you need to create a public URL from a config/locale data file, create a
 * custom exporter that exports data with all required fields to create public
 * URLs, and use CustomExporterInterface::getSupportedPagePaths()
 */
interface PagePathUriResolverInterface {

  /**
   * Given a page path, find its exported URI.
   *
   * It iterates over all StaticPagePathUriResolver plugins until one of
   * them returns a UriInterface object.
   *
   * @param string $pagePath
   *   Page path to be checked.
   * @param string|null $langcode
   *   Optional language id.
   *
   * @return \Drupal\static_export\Exporter\Output\Uri\UriInterface|null
   *   Exported URI.
   */
  public function resolve(string $pagePath, string $langcode = NULL): ?UriInterface;

  /**
   * Tells whether a given page path is a custom path.
   *
   * @param string $pagePath
   *   Page path to be checked.
   * @param string|null $langcode
   *   Optional language id.
   *
   * @return bool
   *   True if given page path is a custom path.
   */
  public function isCustomPath(string $pagePath, string $langcode = NULL): bool;

}
