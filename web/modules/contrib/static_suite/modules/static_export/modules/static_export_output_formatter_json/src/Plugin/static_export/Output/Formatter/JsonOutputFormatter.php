<?php

namespace Drupal\static_export_output_formatter_json\Plugin\static_export\Output\Formatter;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\static_export\Exporter\Output\Formatter\OutputFormatterPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a JSON output formatter.
 *
 * @StaticOutputFormatter(
 *  id = "json",
 *  label = "JSON",
 *  description = "JSON output formatter",
 *  extension = "json",
 *  mimetype = "application/json",
 * )
 */
class JsonOutputFormatter extends PluginBase implements OutputFormatterPluginInterface, ContainerFactoryPluginInterface {

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructor for exporter.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    $plugin_definition,
    ConfigFactoryInterface $config_factory
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get("config.factory")
    );
  }

  /**
   * {@inheritdoc}
   */
  public function format(array $data): string {
    $flagArray = [
      JSON_THROW_ON_ERROR,
      JSON_UNESCAPED_UNICODE,
      JSON_UNESCAPED_SLASHES,
    ];
    if ($this->configFactory->get('static_export_output_formatter_json.settings')
      ->get('pretty_print')) {
      $flagArray[] = JSON_PRETTY_PRINT;
    }
    $flags = array_reduce($flagArray, function ($a, $b) {
      return $a | $b;
    }, 0);

    /** @noinspection JsonEncodingApiUsageInspection */
    return json_encode($data, $flags);
  }

}
