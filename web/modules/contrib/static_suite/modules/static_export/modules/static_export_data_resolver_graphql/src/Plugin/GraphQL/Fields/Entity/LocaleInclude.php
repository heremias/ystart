<?php

namespace Drupal\static_export_data_resolver_graphql\Plugin\GraphQL\Fields\Entity;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\graphql\GraphQL\Execution\ResolveContext;
use Drupal\graphql\Plugin\GraphQL\Fields\FieldPluginBase;
use Drupal\static_export\Exporter\Type\Locale\LocaleExporterPluginManagerInterface;
use GraphQL\Type\Definition\ResolveInfo;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @GraphQLField(
 *   id = "locale_include",
 *   secure = true,
 *   name = "localeInclude",
 *   description = "Path to the locale file to be included",
 *   type = "String",
 *   parents= {"Entity"},
 *   arguments = {
 *     "language" = "LanguageId",
 *     "variant" = "String"
 *   },
 *   nullable = true
 * )
 */
class LocaleInclude extends FieldPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The exporter manager.
   *
   * @var \Drupal\static_export\Exporter\Type\Locale\LocaleExporterPluginManagerInterface
   */
  protected $localeExporterManager;

  /**
   * Constructs a Drupal\Component\Plugin\PluginBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\static_export\Exporter\Type\Locale\LocaleExporterPluginManagerInterface $localeExporterManager
   *   Locale Exporter manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, LanguageManagerInterface $language_manager, LocaleExporterPluginManagerInterface $localeExporterManager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->configuration = $configuration;
    $this->pluginId = $plugin_id;
    $this->pluginDefinition = $plugin_definition;
    $this->languageManager = $language_manager;
    $this->localeExporterManager = $localeExporterManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('language_manager'),
      $container->get('plugin.manager.static_locale_exporter')
    );
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\static_suite\StaticSuiteUserException
   */
  public function resolveValues($value, array $args, ResolveContext $context, ResolveInfo $info) {
    if ($value instanceof EntityInterface) {
      $localeExporter = $this->localeExporterManager->createDefaultInstance();
      $langcodeFromArgs = $args['language'] ?? NULL;
      $langcodeFromContext = $context->getContext('language', $info);
      $langcode = $langcodeFromArgs ?: $langcodeFromContext;
      $localeExporter->setOptions([
        'langcode' => $langcode,
        'variant' => $args['variant'],
      ]);
      $uri = $localeExporter->getUri();
      yield $uri ? $uri->getTarget() : NULL;
    }
  }

}
