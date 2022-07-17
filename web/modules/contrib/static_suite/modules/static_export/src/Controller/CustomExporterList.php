<?php

namespace Drupal\static_export\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Render\Markup;
use Drupal\static_export\Exporter\Type\Custom\CustomExporterPluginManagerInterface;
use Drupal\static_suite\Utility\SettingsUrlResolverInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a listing of custom exporters.
 */
class CustomExporterList extends ControllerBase {

  /**
   * The settings URL resolver.
   *
   * @var \Drupal\static_suite\Utility\SettingsUrlResolverInterface
   */
  protected SettingsUrlResolverInterface $settingsUrlResolver;

  /**
   * The custom exporter manager.
   *
   * @var \Drupal\static_export\Exporter\Type\Custom\CustomExporterPluginManagerInterface
   */
  protected $customExporterManager;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager.
   * @param \Drupal\static_suite\Utility\SettingsUrlResolverInterface $settingsUrlResolver
   *   The settings URL resolver.
   * @param \Drupal\static_export\Exporter\Type\Custom\CustomExporterPluginManagerInterface $customExporterManager
   *   The custom exporter manager.
   */
  public function __construct(
    LanguageManagerInterface $languageManager,
    SettingsUrlResolverInterface $settingsUrlResolver,
    CustomExporterPluginManagerInterface $customExporterManager,
  ) {
    $this->languageManager = $languageManager;
    $this->settingsUrlResolver = $settingsUrlResolver;
    $this->customExporterManager = $customExporterManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('language_manager'),
      $container->get('static_suite.settings_url_resolver'),
      $container->get('plugin.manager.static_custom_exporter'),
    );
  }

  /**
   * Get a list of custom exporters.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function listCustomExporters(): array {

    $tableHeader = [
      'id' => $this->t('ID'),
      'name' => $this->t('Name'),
      'description' => $this->t('Description'),
      'supported_page_paths' => $this->t('Supported page paths (*)'),
      'configuration' => $this->t('Configuration'),
    ];
    $definitions = $this->customExporterManager->getDefinitions();
    $tableRows = NULL;
    foreach ($definitions as $pluginId => $pluginDefinition) {
      $exporter = $this->customExporterManager->createInstance($pluginId);
      $languages = $this->languageManager->getLanguages();
      $supportedPagePathsByLanguage = [];
      foreach ($languages as $language) {
        $langcode = $language->getId();
        $supportedPagePaths = $exporter->getSupportedPagePaths($langcode);
        if (count($supportedPagePaths) > 0) {
          $supportedPagePathsByLanguage[$langcode] = $supportedPagePaths;
        }
      }

      $supportedPagePathsString = '';
      foreach ($supportedPagePathsByLanguage as $langcode => $supportedPagePaths) {
        foreach ($supportedPagePaths as $key => $value) {
          $supportedPagePathsString .= "$langcode: $key ---> $value<br/>";
        }
      }

      $row = [
        'id' => $pluginId,
        'name' => $pluginDefinition['label'],
        'description' => $pluginDefinition['description'],
        'supported_page_paths' => $supportedPagePathsString === '' ? '--' : Markup::create('<div class="nowrap">' . $supportedPagePathsString . '</div>'),
      ];

      // Add $configUrl to $row.
      $configUrl = $this->settingsUrlResolver->setRoutePrefix('static_exporter.custom.')
        ->setRouteKey($pluginId)
        ->setModule($pluginDefinition['provider'])
        ->resolve();
      if ($configUrl) {
        $row['configuration']['data'] = [
          '#title' => $this->t('Edit configuration'),
          '#type' => 'link',
          '#url' => $configUrl,
        ];
      }
      else {
        $row['configuration']['data'] = $this->t('No configuration available');
      }
      $tableRows[$pluginId] = $row;
    }

    return [
      '#type' => 'table',
      '#header' => $tableHeader,
      '#rows' => $tableRows,
      '#prefix' => '<p>' . $this->t(
          'Custom exporters are plugins annotated with %annotation annotation. They provide custom functionality and allow a builder to export data that is not among entities, configuration objects or locale strings.',
          ['%annotation' => '@staticCustomExporter']
      ) . '</p>',
      '#suffix' => '<p>' . $this->t(
          '(*) There are some cases where a data file exported by a custom exporter is used to view a page that is not present in Drupal. This is the case when 1) a page is manually created at build time by a SSG, without any Drupal counterpart; or 2) a preview module, like static_preview_gatsby_instant, needs to know where is the exported file stored. For such cases, we should define the relationship between paths (URIs or aliases) and data files. That relationship is defined as an array where the key is a Regular Expression to define supported paths and value is a replacement for the previous Regex pointing to a data file inside Static Export data directory. E.g.- ["^\/my-manual-page" --- /custom/pages/my-manual-page.json]'
      ) . '</p>',
    ];

  }

}
