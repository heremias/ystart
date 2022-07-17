<?php

namespace Drupal\static_export_output_formatter_xml\Plugin\static_export\Output\Formatter;

use DOMDocument;
use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\static_export\Exporter\Output\Formatter\OutputFormatterPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Provides a XML output formatter.
 *
 * @StaticOutputFormatter(
 *  id = "xml",
 *  label = "XML",
 *  description = "XML output formatter",
 *  extension = "xml",
 *  mimetype = "text/xml",
 * )
 */
class XmlOutputFormatter extends PluginBase implements OutputFormatterPluginInterface, ContainerFactoryPluginInterface {

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Serializer.
   *
   * @var \Symfony\Component\Serializer\Serializer
   */
  protected $serializer;

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
   *   The configuration factory.
   * @param \Symfony\Component\Serializer\SerializerInterface $serializer
   *   Serializer.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    $plugin_definition,
    ConfigFactoryInterface $config_factory,
    SerializerInterface $serializer
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->configFactory = $config_factory;
    $this->serializer = $serializer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get("config.factory"),
      $container->get("serializer")
    );
  }

  /**
   * {@inheritdoc}
   */
  public function format(array $data): string {
    // Flatten array one level to avoid "<data><data>".
    if (count($data) === 1 && isset($data['data']) && is_array($data['data'])) {
      $data = $data['data'];
    }

    // Avoid errors with missing namespaces.
    $data = $this->changeKey($data, function ($key) {
      return str_replace(':', '_', $key);
    });

    $serializedData = $this->serializer->serialize($data, 'xml', ['xml_root_node_name' => 'data']);
    $serializedData = preg_replace('/\s+/', ' ', $serializedData);
    $serializedData = preg_replace('/>\s+/', '>', $serializedData);
    $serializedData = preg_replace('/\s+</', '<', $serializedData);
    $serializedData = preg_replace('/<!\[CDATA\[\s+/', '<![CDATA[', $serializedData);
    $serializedData = preg_replace('/\s+]]>/', ']]>', $serializedData);

    if ($this->configFactory->get('static_export_output_formatter_xml.settings')
      ->get('pretty_print')) {
      $xml = @simplexml_load_string($serializedData);
      if ($xml) {
        $dom = new DOMDocument('1.0');
        $dom->preserveWhiteSpace = FALSE;
        $dom->formatOutput = TRUE;
        $dom->loadXML($xml->asXML());
        $serializedData = $dom->saveXML();
      }
    }

    return $serializedData;
  }

  /**
   * Recursively changes array keys.
   *
   * @param array $arr
   *   Array to be converted.
   * @param array $keySetOrCallBack
   *   Key set (['old-key' => 'new-key']) or callback function to be executed
   *   for each array item.
   *
   * @return array
   */
  protected function changeKey(array $arr, $keySetOrCallBack = []): array {
    $newArr = [];
    foreach ($arr as $k => $v) {
      if (is_callable($keySetOrCallBack)) {
        $key = $keySetOrCallBack($k, $v);
      }
      else {
        $key = $keySetOrCallBack[$k] ?? $k;
      }
      $newArr[$key] = is_array($v) ? $this->changeKey($v, $keySetOrCallBack) : $v;
    }
    return $newArr;
  }

}
