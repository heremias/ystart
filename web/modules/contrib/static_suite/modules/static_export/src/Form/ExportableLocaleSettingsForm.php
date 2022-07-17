<?php

namespace Drupal\static_export\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\static_export\Exporter\Output\Config\ExporterOutputConfigFactoryInterface;
use Drupal\static_export\Exporter\Output\Formatter\OutputFormatterPluginManagerInterface;
use Drupal\static_export\Exporter\Type\Locale\LocaleExporterPluginManagerInterface;
use Drupal\static_suite\Utility\SettingsUrlResolverInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form handler for exportable locales.
 */
class ExportableLocaleSettingsForm extends OutputFormatterDependentConfigFormBase {

  use ConstrainedExporterSettingsFormTrait;

  /**
   * The locale exporter manager.
   *
   * @var \Drupal\static_export\Exporter\Type\Locale\LocaleExporterPluginManagerInterface
   */
  protected $localeExporterManager;

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
   *   The factory for configuration objects.
   * @param \Drupal\static_suite\Utility\SettingsUrlResolverInterface $settingsUrlResolver
   *   The settings URL resolver.
   * @param \Drupal\static_export\Exporter\Type\Locale\LocaleExporterPluginManagerInterface $localeExporterManager
   *   The locale exporter manager.
   * @param \Drupal\static_export\Exporter\Output\Formatter\OutputFormatterPluginManagerInterface $outputFormatterManager
   *   The static output formatter manager.
   * @param \Drupal\static_export\Exporter\Output\Config\ExporterOutputConfigFactoryInterface $localeExporterOutputConfigFactory
   *   The locale output configuration factory.
   */
  public function __construct(ConfigFactoryInterface $configFactory, SettingsUrlResolverInterface $settingsUrlResolver, LocaleExporterPluginManagerInterface $localeExporterManager, OutputFormatterPluginManagerInterface $outputFormatterManager, ExporterOutputConfigFactoryInterface $localeExporterOutputConfigFactory) {
    parent::__construct($configFactory);
    $this->settingsUrlResolver = $settingsUrlResolver;
    $this->localeExporterManager = $localeExporterManager;
    $this->outputFormatterManager = $outputFormatterManager;
    $this->localeExporterOutputConfigFactory = $localeExporterOutputConfigFactory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('static_suite.settings_url_resolver'),
      $container->get('plugin.manager.static_locale_exporter'),
      $container->get('plugin.manager.static_output_formatter'),
      $container->get('static_export.locale_exporter_output_config_factory'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'static_export_exportable_locale_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['static_export.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('static_export.settings');

    $form['status_container'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Status'),
    ];

    $form['status_container']['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Export operations enabled'),
      '#description' => $this->t('Check this option to enable export operations for locales.'),
      '#default_value' => $config->get('exportable_locale.enabled'),
    ];

    $form['exporter_container'] = [
      '#markup' => '<p>' . $this->t('Select the exporter to be used when exporting locale data. Only exporters annotated with <code>@StaticLocaleExporter</code> are available.') . '</p>',
      '#type' => 'details',
      '#title' => $this->t('Exporter'),
      '#open' => TRUE,
    ];

    $header = [
      'id' => $this->t('ID'),
      'name' => $this->t('Name'),
      'description' => $this->t('Description'),
      'configuration' => $this->t('Configuration'),
    ];
    $definitions = $this->localeExporterManager->getDefinitions();
    $options = $this->getExporterDefinitionOptions($definitions);
    $form['exporter_container']['exporter'] = [
      '#type' => 'tableselect',
      '#header' => $header,
      '#options' => $options,
      '#required' => TRUE,
      '#multiple' => FALSE,
      '#empty' => $this->t('No locale exporter available. Please enable a module that provides such exporter or add your own custom plugin.'),
      '#default_value' => $config->get('exportable_locale.exporter'),
    ];

    $form['format_container'] = [
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => $this->t('Format'),
    ];

    $formatDefinitions = $this->outputFormatterManager->getDefinitions();
    $formatOptions = [];
    foreach ($formatDefinitions as $formatterDefinition) {
      $formatOptions[$formatterDefinition['id']] = $formatterDefinition['label'];
    }
    $form['format_container']['format'] = [
      '#type' => 'select',
      '#title' => $this->t('Export format'),
      '#options' => $formatOptions,
      '#default_value' => $config->get('exportable_locale.format'),
      '#prefix' => $this->t('All available locales are automatically exported to the same directory (%dir), using the same export format. File name is derived from each language code.', ['%dir' => '__LANGCODE__/' . $this->localeExporterOutputConfigFactory->getDefaultBaseDir()]),
      '#description' => $this->t('Format for the exported data.'),
      '#required' => TRUE,
    ];

    $form['cli']['export_when_crud_happens_on_cli'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Export locales when a CRUD operation happens on CLI (recommended)'),
      '#default_value' => $config->get('exportable_locale.export_when_crud_happens_on_cli'),
      '#description' => $this->t('It is recommended to export locales from these CRUD operation so there is no mismatch between Drupal database and exported data.'),
      '#required' => FALSE,
    ];

    $form['cli']['request_build_when_crud_exports_on_cli'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Request a build when a CRUD operation exports locales on CLI (not recommended).'),
      '#default_value' => $config->get('exportable_locale.request_build_when_crud_exports_on_cli'),
      '#description' => $this->t('It is not recommended to request a build from a non-interactive process, because it will lead to several builds taking place in an uncontrolled way.'),
      '#required' => FALSE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\Core\Config\Config $config */
    $config = $this->config('static_export.settings');
    $config
      ->set('exportable_locale.enabled', (bool) $form_state->getValue('enabled'))
      ->set('exportable_locale.exporter', $form_state->getValue('exporter'))
      ->set('exportable_locale.format', $form_state->getValue('format'))
      ->set('exportable_locale.export_when_crud_happens_on_cli', $form_state->getValue('export_when_crud_happens_on_cli'))
      ->set('exportable_locale.request_build_when_crud_exports_on_cli', $form_state->getValue('request_build_when_crud_exports_on_cli'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
