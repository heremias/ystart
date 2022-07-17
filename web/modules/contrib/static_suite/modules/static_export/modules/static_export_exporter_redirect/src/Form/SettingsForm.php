<?php

namespace Drupal\static_export_exporter_redirect\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\static_export\Exporter\Output\Formatter\OutputFormatterPluginManagerInterface;
use Drupal\static_export\Form\OutputFormatterDependentConfigFormBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configuration form for Redirect exporter.
 */
class SettingsForm extends OutputFormatterDependentConfigFormBase {

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
   * @param \Drupal\static_export\Exporter\Output\Formatter\OutputFormatterPluginManagerInterface $outputFormatterManager
   *   The static output formatter manager.
   */
  public function __construct(ConfigFactoryInterface $configFactory, OutputFormatterPluginManagerInterface $outputFormatterManager) {
    parent::__construct($configFactory);
    $this->outputFormatterManager = $outputFormatterManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('plugin.manager.static_output_formatter'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'static_export_exporter_redirect_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['static_export_exporter_redirect.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('static_export_exporter_redirect.settings');

    $form['help'] = [
      '#markup' =>
      '<p>' . $this->t('Redirect data can be exported in two ways:') . '</p>' .
      '<ul>' .
      '<li>' . $this->t('<strong>Raw data</strong>, using the output format of your choice. You should take that raw data and format it to meet your needs during the build phase of your Static Site Generator.') . '</li>' .
      '<li>' . $this->t('<strong>Processed data</strong> in a CSV file. Every redirect is listed on a separate line containing the original path, the new path or URL and the HTTP status code. The order of these parts is configurable, since each hosting service requires a specific format.') . '</li>' .
      '</ul>',
    ];

    $form['format_container'] = [
      '#type' => 'fieldset',
      '#open' => TRUE,
      '#title' => $this->t('Format'),
    ];

    $formatDefinitions = $this->outputFormatterManager->getDefinitions();
    $rawDataFormats = [];
    foreach ($formatDefinitions as $formatterDefinition) {
      $rawDataFormats[$formatterDefinition['id']] = $formatterDefinition['label'];
    }
    $formatOptions = [
      $this->t('Raw data')->render() => $rawDataFormats,
      $this->t('Processed data')->render() => [
        'csv' => 'CSV (one redirect rule per line)',
      ],
    ];

    $form['format_container']['format'] = [
      '#type' => 'select',
      '#title' => $this->t('Export format'),
      '#options' => $formatOptions,
      '#default_value' => $config->get('format'),
      '#description' => $this->t('Format for the exported data.'),
      '#required' => TRUE,
    ];

    $form['format_container']['csv_pattern'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Line pattern for the CSV format'),
      '#required' => FALSE,
      '#description' =>
      '<p>' .
      $this->t('Use the following tokens to define how each redirect should appear in the CSV file:') .
      '</p><ul><li>[' .
      implode(']</li><li>[', static_export_exporter_redirect_get_csv_tokens()) .
      ']</li></ul><p>' .
      $this->t('The tab character (@tab) can be used as a separator.', ['@tab' => '\t']) .
      '</p>' .
      $this->t('A typical pattern is %pattern.', ['%pattern' => static_export_exporter_redirect_get_default_csv_pattern()]) .
      '</p>',
      '#default_value' => $config->get('csv.pattern') ?? static_export_exporter_redirect_get_default_csv_pattern(),
      '#states' => [
        'visible' => [
          ':input[name="format"]' => ['value' => 'csv'],
        ],
        'required' => [
          ':input[name="format"]' => ['value' => 'csv'],
        ],
      ],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $format = $form_state->getValue('format');
    $csvPattern = trim($form_state->getValue('csv_pattern'));
    if ($format === 'csv' && empty($csvPattern)) {
      $form_state->setErrorByName(
        'csv.pattern',
        $this->t('Line pattern for the CSV format is required.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('static_export_exporter_redirect.settings');
    $formatDefinitions = $this->outputFormatterManager->getDefinitions();
    $format = $form_state->getValue('format');

    $dependencies = NULL;
    if (!empty($formatDefinitions[$format]['provider'])) {
      $dependencies = [
        'enforced' => [
          'module' => [
            0 => $formatDefinitions[$format]['provider'],
          ],
        ],
      ];
    }

    $config->set('format', $format);
    $config->set('csv.pattern', $format === 'csv' ? $form_state->getValue('csv_pattern') : NULL);
    if ($dependencies) {
      $config->set('dependencies', $dependencies);
    }
    else {
      $config->clear('dependencies');
    }
    $config->save();

    parent::submitForm($form, $form_state);
  }

}
