<?php

namespace Drupal\static_export_output_formatter_yaml\Plugin\static_export\Output\Formatter;

use Drupal\Component\Plugin\PluginBase;
use Drupal\static_export\Exporter\Output\Formatter\OutputFormatterPluginInterface;

/**
 * Provides a YAML output formatter.
 *
 * This formatter is provided only for demonstration purposes. It is not used
 * for data includes or for any other advanced functionality.
 *
 * That means that, when a functionality needs to be developed for
 * a StaticOutputFormatter, it will only be done for JSON and XML formats,
 * the two "main" formats that Static Suite supports.
 *
 * @StaticOutputFormatter(
 *  id = "yaml",
 *  label = "YAML",
 *  description = "YAML output formatter",
 *  extension = "yaml",
 *  mimetype = "text/yaml",
 * )
 */
class YamlOutputFormatter extends PluginBase implements OutputFormatterPluginInterface {

  /**
   * {@inheritdoc}
   */
  public function format(array $data): string {
    return yaml_emit($data);
  }

}
