<?php

namespace Drupal\static_export_data_resolver_graphql\Plugin\GraphQL\Fields\Entity;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\graphql\GraphQL\Execution\ResolveContext;
use Drupal\graphql\Plugin\GraphQL\Fields\FieldPluginBase;
use Drupal\static_export\Exporter\Type\Config\ConfigExporterPluginManagerInterface;
use GraphQL\Type\Definition\ResolveInfo;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @GraphQLField(
 *   id = "config_include",
 *   secure = true,
 *   name = "configInclude",
 *   description = "Path to the config file to be included",
 *   type = "String",
 *   parents= {"Entity"},
 *   arguments = {
 *     "name" = "String!",
 *     "language" = "LanguageId",
 *     "variant" = "String"
 *   },
 *   nullable = true
 * )
 */
class ConfigInclude extends FieldPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The config exporter manager.
   *
   * @var \Drupal\static_export\Exporter\Type\Config\ConfigExporterPluginManagerInterface
   */
  protected $configExporterManager;

  /**
   * Constructs a Drupal\Component\Plugin\PluginBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\static_export\Exporter\Type\Config\ConfigExporterPluginManagerInterface $configExporterManager
   *   Config exporter manager.
   */
  public function __construct(array $configuration, string $plugin_id, $plugin_definition, ConfigExporterPluginManagerInterface $configExporterManager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->configuration = $configuration;
    $this->pluginId = $plugin_id;
    $this->pluginDefinition = $plugin_definition;
    $this->configExporterManager = $configExporterManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.static_config_exporter')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function isLanguageAwareField() {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\static_suite\StaticSuiteUserException
   */
  public function resolveValues($value, array $args, ResolveContext $context, ResolveInfo $info) {
    if ($value instanceof EntityInterface) {
      $configExporter = $this->configExporterManager->createDefaultInstance();
      $langcodeFromArgs = $args['language'] ?? NULL;
      $langcodeFromContext = $context->getContext('language', $info);
      $langcode = $langcodeFromArgs ?: $langcodeFromContext;
      $configExporter->setOptions([
        'name' => $args['name'],
        'langcode' => $langcode,
        'variant' => $args['variant'],
      ]);
      $uri = $configExporter->getUri();
      yield $uri ? $uri->getTarget() : NULL;
    }
  }

}
