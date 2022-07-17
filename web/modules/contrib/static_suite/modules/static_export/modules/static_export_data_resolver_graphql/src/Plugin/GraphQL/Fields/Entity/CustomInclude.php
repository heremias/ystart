<?php

namespace Drupal\static_export_data_resolver_graphql\Plugin\GraphQL\Fields\Entity;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\graphql\GraphQL\Execution\ResolveContext;
use Drupal\graphql\Plugin\GraphQL\Fields\FieldPluginBase;
use Drupal\static_export\Exporter\Output\Config\ExporterOutputConfigFactoryInterface;
use GraphQL\Type\Definition\ResolveInfo;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * CustomInclude field.
 *
 * @GraphQLField(
 *   id = "custom_include",
 *   secure = true,
 *   name = "customInclude",
 *   description = "Path to the custom file to be included",
 *   type = "String",
 *   parents= {"Entity"},
 *   arguments = {
 *     "basedir" = "String",
 *     "dir" = "String!",
 *     "filename" = "String!",
 *     "extension" = "String",
 *     "language" = "LanguageIdAll"
 *   },
 *   nullable = true
 * )
 */
class CustomInclude extends FieldPluginBase implements ContainerFactoryPluginInterface {

  /**
   * Config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The custom exporter output config factory.
   *
   * @var \Drupal\static_export\Exporter\Output\Config\ExporterOutputConfigFactoryInterface
   */
  protected $customExporterOutputConfigFactory;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * Constructs a Drupal\Component\Plugin\PluginBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Config factory.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager.
   * @param \Drupal\static_export\Exporter\Output\Config\ExporterOutputConfigFactoryInterface $customExporterOutputConfigFactory
   *   The custom exporter output config factory.
   */
  public function __construct(array $configuration, string $plugin_id, $plugin_definition, ConfigFactoryInterface $config_factory, LanguageManagerInterface $languageManager, ExporterOutputConfigFactoryInterface $customExporterOutputConfigFactory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->configuration = $configuration;
    $this->pluginId = $plugin_id;
    $this->pluginDefinition = $plugin_definition;
    $this->configFactory = $config_factory;
    $this->languageManager = $languageManager;
    $this->customExporterOutputConfigFactory = $customExporterOutputConfigFactory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory'),
      $container->get('language_manager'),
      $container->get('static_export.custom_exporter_output_config_factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function resolveValues($value, array $args, ResolveContext $context, ResolveInfo $info) {
    if ($value instanceof EntityInterface) {
      $langcodeFromArgs = $args['language'] ?? NULL;
      $langcodeFromContext = $context->getContext('language', $info);
      $langCode = $langcodeFromArgs ?: $langcodeFromContext;
      $language = $this->languageManager->getLanguage($langCode);
      $outputConfig = $this->customExporterOutputConfigFactory->create($args['dir'], $args['filename'], $args['extension'], $language);
      if (isset($args['basedir'])) {
        $outputConfig->setBaseDir($args['basedir']);
      }
      $uri = $outputConfig->uri();
      yield $uri ? $uri->getTarget() : NULL;
    }
  }

}
