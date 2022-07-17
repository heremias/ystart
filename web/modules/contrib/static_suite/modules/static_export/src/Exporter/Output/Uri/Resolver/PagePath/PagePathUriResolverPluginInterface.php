<?php

namespace Drupal\static_export\Exporter\Output\Uri\Resolver\PagePath;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\static_export\Exporter\Output\Uri\UriInterface;

/**
 * Interface for URI resolvers of page paths.
 */
interface PagePathUriResolverPluginInterface extends PluginInspectionInterface, ContainerFactoryPluginInterface {

  /**
   * Given a page path, find its exported URI.
   *
   * @param string $pagePath
   *   Page path to be checked.
   * @param string $langcode
   *   Language id.
   *
   * @return \Drupal\static_export\Exporter\Output\Uri\UriInterface|null
   *   Exported URI.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function resolve(string $pagePath, string $langcode): ?UriInterface;

}
