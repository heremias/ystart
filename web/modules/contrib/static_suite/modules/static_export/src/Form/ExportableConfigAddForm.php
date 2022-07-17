<?php

namespace Drupal\static_export\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\static_export\Exporter\Output\Formatter\OutputFormatterPluginManagerInterface;
use Drupal\static_export\Exporter\Type\Config\ConfigExporterPluginManagerInterface;
use Drupal\static_export\Messenger\MessengerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form handler for adding exportable configurations.
 */
class ExportableConfigAddForm extends OutputFormatterDependentConfigFormBase {

  /**
   * The config exporter manager.
   *
   * @var \Drupal\static_export\Exporter\Type\Config\ConfigExporterPluginManagerInterface
   */
  protected $configExporterManager;

  /**
   * The static exporter messenger.
   *
   * @var \Drupal\static_export\Messenger\MessengerInterface
   */
  protected $staticExportMessenger;

  /**
   * {@inheritdoc}
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The factory for configuration objects.
   * @param \Drupal\static_export\Exporter\Type\Config\ConfigExporterPluginManagerInterface $configExporterManager
   *   The config exporter manager.
   * @param \Drupal\static_export\Exporter\Output\Formatter\OutputFormatterPluginManagerInterface $outputFormatterManager
   *   The output formatter manager.
   * @param \Drupal\static_export\Messenger\MessengerInterface $staticExportMessenger
   *   The static exporter messenger.
   */
  public function __construct(ConfigFactoryInterface $configFactory, ConfigExporterPluginManagerInterface $configExporterManager, OutputFormatterPluginManagerInterface $outputFormatterManager, MessengerInterface $staticExportMessenger) {
    parent::__construct($configFactory);
    $this->configExporterManager = $configExporterManager;
    $this->outputFormatterManager = $outputFormatterManager;
    $this->staticExportMessenger = $staticExportMessenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('plugin.manager.static_config_exporter'),
      $container->get('plugin.manager.static_output_formatter'),
      $container->get('static_export.messenger')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'static_export_exportable_config_add';
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
    $currentConfigObjects = $config->get('exportable_config.objects_to_export');

    $allConfigNames = $this->configFactory->listAll();
    $options = [];
    foreach ($allConfigNames as $configName) {
      if (!is_array($currentConfigObjects) || !in_array($configName, $currentConfigObjects, TRUE)) {
        $options[$configName] = $configName;
      }
    }

    $form['object_to_export'] = [
      '#type' => 'select',
      '#options' => $options,
      '#title' => $this->t('Select configuration object to export'),
      '#required' => TRUE,
      '#suffix' => '<p>' . $this->t('This action will export the configuration object of your choice and could potentially trigger a build.') . '</p>',
    ];

    $form = parent::buildForm($form, $form_state);
    $form['actions']['submit']['#value'] = $this->t('Add');

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('static_export.settings');
    $currentConfigObjects = $config->get('exportable_config.objects_to_export');

    if (is_array($currentConfigObjects) && in_array($form_state->getValue('object_to_export'), $currentConfigObjects, TRUE)) {
      $form_state->setError(
        $form,
        $this->t('Configuration "@config" is already exported.', ['@config' => $form_state->getValue('object_to_export')]));
    }

    $configExporters = $this->configExporterManager->getDefinitions();
    if (count($configExporters) === 0) {
      $form_state->setError(
        $form,
        $this->t(
          'A compatible exporter is required to be able to export a configuration object. Please visit the <a href="@url">settings page</a>.',
          [
            '@url' => Url::fromRoute('static_export.exportable_config.settings')
              ->toString(),
          ]
        )
      );
    }

    $outputFormatters = $this->outputFormatterManager->getDefinitions();
    if (count($outputFormatters) === 0) {
      $form_state->setError(
        $form,
        $this->t(
          'No output formatter module enabled. Please visit the <a href="@url">module list page</a>.',
          ['@url' => Url::fromRoute('system.modules_list')->toString()]
        )
      );
    }

    if (empty($config->get('exportable_config.format'))) {
      $form_state->setError(
        $form,
        $this->t('No export format configured. You need to define a export format in the <a href="@url">settings</a> page.', [
          '@url' => Url::fromRoute('static_export.exportable_config.settings')
            ->toString(),
        ]));
    }
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\static_suite\StaticSuiteException
   * @throws \Drupal\static_suite\StaticSuiteUserException
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('static_export.settings');
    $configObjects = $config->get('exportable_config.objects_to_export');
    $configObjects[] = $form_state->getValue('object_to_export');
    sort($configObjects);
    $config
      ->set('exportable_config.objects_to_export', array_values($configObjects))
      ->save();

    // Tell the config exporter to export this object data.
    $fileCollectionGroup = $this->configExporterManager->getDefaultInstance()
      ->export(['name' => $form_state->getValue('object_to_export')]);
    $this->staticExportMessenger->addFileCollectionGroup($fileCollectionGroup);

    $this->messenger()
      ->addMessage($this->t('The configuration %label has been saved.', [
        '%label' => $form_state->getValue('object_to_export'),
      ]));

    $form_state->setRedirect('static_export.exportable_config.collection');
  }

}
