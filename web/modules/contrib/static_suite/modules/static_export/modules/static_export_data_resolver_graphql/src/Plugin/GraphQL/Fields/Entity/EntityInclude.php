<?php

namespace Drupal\static_export_data_resolver_graphql\Plugin\GraphQL\Fields\Entity;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\graphql\GraphQL\Execution\ResolveContext;
use Drupal\graphql\Plugin\GraphQL\Fields\FieldPluginBase;
use Drupal\static_export\Exporter\Type\Entity\EntityExporterPluginManagerInterface;
use GraphQL\Type\Definition\ResolveInfo;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @GraphQLField(
 *   id = "entity_include",
 *   secure = true,
 *   name = "entityInclude",
 *   description = "Path to the entity file to be included",
 *   type = "String",
 *   parents= {"Entity"},
 *   arguments = {
 *     "variant" = "String",
 *     "keepOriginalLanguage" = "Boolean"
 *   },
 *   nullable = true
 * )
 */
class EntityInclude extends FieldPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The entity exporter manager.
   *
   * @var \Drupal\static_export\Exporter\Type\Entity\EntityExporterPluginManagerInterface
   */
  protected $entityExporterPluginManager;

  /**
   * Constructs a Drupal\Component\Plugin\PluginBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\static_export\Exporter\Type\Entity\EntityExporterPluginManagerInterface $entityExporterPluginManager
   *   Entity exporter manager.
   */
  public function __construct(array $configuration, string $plugin_id, $plugin_definition, EntityExporterPluginManagerInterface $entityExporterPluginManager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->configuration = $configuration;
    $this->pluginId = $plugin_id;
    $this->pluginDefinition = $plugin_definition;
    $this->entityExporterPluginManager = $entityExporterPluginManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.static_entity_exporter')
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

      // When a ConfigEntity is being used inside an EntityInclude, it does not
      // not properly inherit the language from the GraphQL context, leading to
      // a mismatch of the languages being used. For example, a node of language
      // "en-gb" contains a paragraph, and the paragraph contains a menu of
      // language "es". When using an entityInclude for that paragraph, and
      // another entityInclude for that menu, the resulting path for the menu
      // file starts with 'es' instead of 'en-gb', because 'es' is the language
      // of the menu, as selected in menu's language select.
      // Therefore, we force the use of the language coming from the GraphQL
      // context, to make sure all paths are under the same language.
      if (!$args['keepOriginalLanguage'] && $value instanceof ConfigEntityInterface) {
        $langcode = $context->getContext('language', $info);
        $value->set('langcode', $langcode);
      }

      $entityExporter = $this->entityExporterPluginManager->createDefaultInstance();
      $entityExporter->setOptions([
        'entity' => $value,
        'variant' => $args['variant'],
      ]);
      $uri = $entityExporter->getUri();
      yield $uri ? $uri->getTarget() : NULL;
    }
  }

}
