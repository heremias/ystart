<?php

namespace Drupal\static_export\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\static_export\Exporter\Output\Config\ExporterOutputConfigFactoryInterface;
use Drupal\static_export\Exporter\Output\Formatter\OutputFormatterPluginManagerInterface;
use Drupal\static_export\Exporter\Type\Config\ConfigExporterPluginManagerInterface;
use Drupal\static_suite\Utility\SettingsUrlResolverInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form handler for exportable configurations.
 */
class ExportableConfigSettingsForm extends OutputFormatterDependentConfigFormBase {

  use ConstrainedExporterSettingsFormTrait;

  /**
   * The config exporter manager.
   *
   * @var \Drupal\static_export\Exporter\Type\Config\ConfigExporterPluginManagerInterface
   */
  protected $configExporterManager;

  /**
   * The config output configuration factory.
   *
   * @var \Drupal\static_export\Exporter\Output\Config\ExporterOutputConfigFactoryInterface
   */
  protected $configExporterOutputConfigFactory;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The factory for configuration objects.
   * @param \Drupal\static_suite\Utility\SettingsUrlResolverInterface $settingsUrlResolver
   *   The settings URL resolver.
   * @param \Drupal\static_export\Exporter\Type\Config\ConfigExporterPluginManagerInterface $configExporterManager
   *   The config exporter manager.
   * @param \Drupal\static_export\Exporter\Output\Formatter\OutputFormatterPluginManagerInterface $outputFormatterManager
   *   The static output formatter manager.
   * @param \Drupal\static_export\Exporter\Output\Config\ExporterOutputConfigFactoryInterface $configExporterOutputConfigFactory
   *   The config output configuration factory.
   */
  public function __construct(ConfigFactoryInterface $configFactory, SettingsUrlResolverInterface $settingsUrlResolver, ConfigExporterPluginManagerInterface $configExporterManager, OutputFormatterPluginManagerInterface $outputFormatterManager, ExporterOutputConfigFactoryInterface $configExporterOutputConfigFactory) {
    parent::__construct($configFactory);
    $this->settingsUrlResolver = $settingsUrlResolver;
    $this->configExporterManager = $configExporterManager;
    $this->outputFormatterManager = $outputFormatterManager;
    $this->configExporterOutputConfigFactory = $configExporterOutputConfigFactory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('static_suite.settings_url_resolver'),
      $container->get('plugin.manager.static_config_exporter'),
      $container->get('plugin.manager.static_output_formatter'),
      $container->get('static_export.config_exporter_output_config_factory'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'static_export_exportable_config_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['static_export.settings'];
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
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
      '#description' => $this->t('Check this option to enable export operations for configurations.'),
      '#default_value' => $config->get('exportable_config.enabled'),
    ];

    $form['exporter_container'] = [
      '#markup' => '<p>' . $this->t('Select the exporter to be used when exporting configuration data. Only exporters annotated with <code>@StaticConfigExporter</code> are available.') . '</p>',
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
    $definitions = $this->configExporterManager->getDefinitions();
    $options = $this->getExporterDefinitionOptions($definitions);
    $form['exporter_container']['exporter'] = [
      '#type' => 'tableselect',
      '#header' => $header,
      '#options' => $options,
      '#required' => TRUE,
      '#multiple' => FALSE,
      '#empty' => $this->t('No configuration exporter available. Please enable a module that provides such exporter or add your own custom plugin.'),
      '#default_value' => $config->get('exportable_config.exporter'),
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
      '#default_value' => $config->get('exportable_config.format'),
      '#prefix' => $this->t('All exportable configurations are exported to the same folder ("%dir" inside the data directory), using the same export format. File name is derived from each exported configuration object.', ['%dir' => '__LANGCODE__/' . $this->configExporterOutputConfigFactory->getDefaultBaseDir()]),
      '#description' => $this->t('Format for the exported data.'),
      '#required' => TRUE,
    ];

    $form['cli'] = [
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => $this->t('CLI options'),
      '#description' => $this->t('Options for CRUD operations that happens outside a web server (e.g.- a cron or a drush command editing or adding entities, etc)'),
    ];

    $form['cli']['export_when_crud_happens_on_cli'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Export configurations when a CRUD operation happens on CLI (not recommended)'),
      '#default_value' => $config->get('exportable_config.export_when_crud_happens_on_cli'),
      '#description' => $this->t('Contrary to exportable entities and locales, exporting configurations inside a non-interactive process could lead to problems. For example, running a "drush cim" command would trigger export operations that could be broken due to some configuration not being already fully updated. Therefore, it is recommended to keep this option disabled and to manually export configuration objects after "drush cim" has finished, using "drush static-export:export-config".'),
      '#required' => FALSE,
    ];

    $form['cli']['request_build_when_crud_exports_on_cli'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Request a build when a CRUD operation exports configurations on CLI (not recommended).'),
      '#default_value' => $config->get('exportable_config.request_build_when_crud_exports_on_cli'),
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
      ->set('exportable_config.enabled', (bool) $form_state->getValue('enabled'))
      ->set('exportable_config.exporter', $form_state->getValue('exporter'))
      ->set('exportable_config.format', $form_state->getValue('format'))
      ->set('exportable_config.export_when_crud_happens_on_cli', $form_state->getValue('export_when_crud_happens_on_cli'))
      ->set('exportable_config.request_build_when_crud_exports_on_cli', $form_state->getValue('request_build_when_crud_exports_on_cli'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
