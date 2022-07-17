<?php

namespace Drupal\static_export\Exporter\Type\Config\Output\Uri\Resolver;

use Drupal\static_export\Exporter\Output\Uri\Resolver\ExporterUriResolverInterface;

/**
 * Interface for a URI resolver of exported configuration objects.
 */
interface ConfigExporterUriResolverInterface extends ExporterUriResolverInterface {

  /**
   * Set configuration object name to work with.
   *
   * @param string $configName
   *   Configuration object name to work with.
   *
   * @return ConfigExporterUriResolverInterface
   *   Return $this so this method can be chainable
   */
  public function setConfigName(string $configName): ConfigExporterUriResolverInterface;

}
