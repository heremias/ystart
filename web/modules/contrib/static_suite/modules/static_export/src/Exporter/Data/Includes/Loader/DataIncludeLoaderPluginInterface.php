<?php

namespace Drupal\static_export\Exporter\Data\Includes\Loader;

use Drupal\Component\Plugin\PluginInspectionInterface;

/**
 * Defines an interface for Data include loader plugins.
 */
interface DataIncludeLoaderPluginInterface extends PluginInspectionInterface {

  /**
   * Load data includes.
   *
   * Find all includes in $data and replace them with their contents.
   *
   * @param string $data
   *   The string where data includes appear.
   *
   * @return string
   *   The processed string.
   */
  public function load(string $data): string;

}
