<?php

namespace Drupal\static_export\Exporter\Type\Entity;

use Drupal\static_export\Exporter\ExporterPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base exporter for all entity exporters.
 */
abstract class EntityExporterPluginBase extends ExporterPluginBase implements EntityExporterPluginInterface {

  /**
   * {@inheritdoc}
   *
   * Get the specific ExporterOutputConfigFactory already configured for this
   * type of exporter. That configuration is done in this module's services.yml.
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->exporterOutputConfigFactory = $container->get("static_export.entity_exporter_output_config_factory");
    return $instance;
  }

}
