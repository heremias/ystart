<?php

namespace Drupal\static_export\Exporter\Type\Locale;

use Drupal\static_export\Exporter\ExporterPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base exporter for all locale exporters.
 */
abstract class LocaleExporterPluginBase extends ExporterPluginBase implements LocaleExporterPluginInterface {

  /**
   * {@inheritdoc}
   *
   * Get the specific ExporterOutputConfigFactory already configured for this
   * type of exporter. That configuration is done in this module's services.yml.
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->exporterOutputConfigFactory = $container->get("static_export.locale_exporter_output_config_factory");
    return $instance;
  }

}
