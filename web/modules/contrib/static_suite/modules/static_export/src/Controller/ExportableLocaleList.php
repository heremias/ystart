<?php

namespace Drupal\static_export\Controller;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Url;
use Drupal\static_export\Exporter\Output\Config\ExporterOutputConfigFactoryInterface;
use Drupal\static_export\Exporter\Type\Locale\LocaleExporterPluginManagerInterface;
use Drupal\static_export\Exporter\Type\Locale\Output\Uri\Resolver\LocaleExporterUriResolverInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a listing of Exportable locales.
 */
class ExportableLocaleList extends ControllerBase {

  /**
   * The locale exporter manager.
   *
   * @var \Drupal\static_export\Exporter\Type\Locale\LocaleExporterPluginManagerInterface
   */
  protected $localeExporterManager;

  /**
   * The exported locale file resolver.
   *
   * @var \Drupal\static_export\Exporter\Type\Locale\Output\Uri\Resolver\LocaleExporterUriResolverInterface
   */
  protected $localeExporterUriResolver;

  /**
   * The locale output configuration factory.
   *
   * @var \Drupal\static_export\Exporter\Output\Config\ExporterOutputConfigFactoryInterface
   */
  protected $localeExporterOutputConfigFactory;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager.
   * @param \Drupal\static_export\Exporter\Type\Locale\LocaleExporterPluginManagerInterface $localeExporterManager
   *   The locale exporter manager.
   * @param \Drupal\static_export\Exporter\Type\Locale\Output\Uri\Resolver\LocaleExporterUriResolverInterface $localeExporterUriResolver
   *   Locale exporter path resolver.
   * @param \Drupal\static_export\Exporter\Output\Config\ExporterOutputConfigFactoryInterface $localeExporterOutputConfigFactory
   *   The locale output configuration factory.
   */
  public function __construct(ConfigFactoryInterface $configFactory, LanguageManagerInterface $languageManager, LocaleExporterPluginManagerInterface $localeExporterManager, LocaleExporterUriResolverInterface $localeExporterUriResolver, ExporterOutputConfigFactoryInterface $localeExporterOutputConfigFactory) {
    $this->configFactory = $configFactory;
    $this->languageManager = $languageManager;
    $this->localeExporterManager = $localeExporterManager;
    $this->localeExporterUriResolver = $localeExporterUriResolver;
    $this->localeExporterOutputConfigFactory = $localeExporterOutputConfigFactory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('language_manager'),
      $container->get('plugin.manager.static_locale_exporter'),
      $container->get('static_export.locale_exporter_uri_resolver'),
      $container->get('static_export.locale_exporter_output_config_factory'),
    );
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function listExportableLocales() {
    if (!$this->configFactory->get('static_export.settings')
      ->get('exportable_locale.enabled')) {
      $configUrl = Url::fromRoute('static_export.exportable_locale.settings', [], ['absolute' => FALSE])
        ->toString();
      $this->messenger()
        ->addWarning($this->t('Export operations for locales are disabled. Please, enable them in the <a href="@url">settings</a> page.', ['@url' => $configUrl]));
    }

    $tableHeader = [
      'language' => $this->t('Language'),
      'uri' => $this->t('Export file'),
      'modification_date' => $this->t('Last export date'),
      'operations' => $this->t('Operations'),
    ];
    $tableRows = [];

    foreach ($this->languageManager->getLanguages() as $language) {
      $langcode = $language->getId();
      $exportUris = $this->localeExporterUriResolver->setLanguage($langcode)
        ->getUris();
      $lastLangcode = NULL;
      foreach ($exportUris as $exportUri) {
        $modificationDate = @filemtime($exportUri);
        $isSubRow = $lastLangcode === $langcode;
        $tableRows[] = [
          'language' => $isSubRow ? '' : $language->getName() . ' (' . $langcode . ')',
          'uri' => $exportUri->getTarget(),
          'modification_date' => $modificationDate ? date('Y-m-d H:i:s', $modificationDate) : $this->t('Not available'),
          'operations' => [
            'data' => [
              '#type' => 'operations',
              '#links' => [
                'view' => [
                  'title' => $this->t('View'),
                  'weight' => 10,
                  'url' => Url::fromUri(file_create_url($exportUri)),
                  'attributes' => [
                    'target' => '_blank',
                  ],
                ],
              ],
            ],
          ],
        ];
        $lastLangcode = $langcode;
      }
    }

    return [
      '#type' => 'table',
      '#header' => $tableHeader,
      '#rows' => $tableRows,
      '#prefix' => '<p>' . $this->t(
          'Each available language is exported to a file located at <em>"@locale_dir"</em>. You can configure its format in the <a href="@settings_url">settings</a> page.',
          [
            '@settings_url' => Url::fromRoute('static_export.exportable_locale.settings')
              ->toString(),
            '@locale_dir' => '__LANGCODE__/' . $this->localeExporterOutputConfigFactory->getDefaultBaseDir(),
          ]
      ) . '</p>',
    ];

  }

}
