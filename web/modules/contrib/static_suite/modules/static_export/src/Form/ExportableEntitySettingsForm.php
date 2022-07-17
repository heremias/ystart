<?php

namespace Drupal\static_export\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\static_export\Exporter\Type\Entity\EntityExporterPluginManagerInterface;
use Drupal\static_suite\Utility\SettingsUrlResolverInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form handler for exportable entities.
 */
class ExportableEntitySettingsForm extends ConfigFormBase {

  use ConstrainedExporterSettingsFormTrait;

  /**
   * The entity exporter manager.
   *
   * @var \Drupal\static_export\Exporter\Type\Entity\EntityExporterPluginManagerInterface
   */
  protected $entityExporterPluginManager;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The factory for configuration objects.
   * @param \Drupal\static_suite\Utility\SettingsUrlResolverInterface $settingsUrlResolver
   *   The settings URL resolver.
   * @param \Drupal\static_export\Exporter\Type\Entity\EntityExporterPluginManagerInterface $entityExporterPluginManager
   *   The entity exporter manager.
   */
  public function __construct(ConfigFactoryInterface $configFactory, SettingsUrlResolverInterface $settingsUrlResolver, EntityExporterPluginManagerInterface $entityExporterPluginManager) {
    parent::__construct($configFactory);
    $this->settingsUrlResolver = $settingsUrlResolver;
    $this->entityExporterPluginManager = $entityExporterPluginManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('static_suite.settings_url_resolver'),
      $container->get('plugin.manager.static_entity_exporter')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'static_export_exportable_entity_settings';
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
      '#description' => $this->t('Check this option to enable export operations for entities.'),
      '#default_value' => $config->get('exportable_entity.enabled'),
    ];

    $form['exporter_container'] = [
      '#markup' => '<p>' . $this->t('Select the exporter to be used when exporting entities. Only exporters annotated with <code>@StaticEntityExporter</code> are available.') . '</p>',
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
    $definitions = $this->entityExporterPluginManager->getDefinitions();
    $options = $this->getExporterDefinitionOptions($definitions);
    $form['exporter_container']['exporter'] = [
      '#type' => 'tableselect',
      '#header' => $header,
      '#options' => $options,
      '#required' => TRUE,
      '#multiple' => FALSE,
      '#empty' => $this->t('No entity exporter available. Please enable a module that provides such exporter or add your own custom plugin.'),
      '#default_value' => $config->get('exportable_entity.exporter'),
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
      ->set('exportable_entity.enabled', (bool) $form_state->getValue('enabled'))
      ->set('exportable_entity.exporter', $form_state->getValue('exporter'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
