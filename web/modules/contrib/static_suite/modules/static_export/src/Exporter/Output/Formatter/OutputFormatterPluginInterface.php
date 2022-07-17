<?php

namespace Drupal\static_export\Exporter\Output\Formatter;

use Drupal\Component\Plugin\PluginInspectionInterface;

/**
 * Defines an interface for Static Output Formatter plugins.
 */
interface OutputFormatterPluginInterface extends PluginInspectionInterface {

  /**
   * Format data.
   *
   * @param array $data
   *   Raw data coming form a resolver.
   *
   * @return string
   *   String with formatted data.
   */
  public function format(array $data): string;

}
