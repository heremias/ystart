<?php

namespace Drupal\static_export\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\static_export\Exporter\Type\Config\ConfigExporterPluginManagerInterface;
use Drupal\static_export\Exporter\Type\Config\Output\Uri\Resolver\ConfigExporterUriResolverInterface;
use Drupal\static_export\Messenger\MessengerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form handler for deleting exportable configurations.
 */
class ExportableConfigDeleteForm extends ConfirmFormBase {

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
   * The config exporter path resolver.
   *
   * @var \Drupal\static_export\Exporter\Type\Config\Output\Uri\Resolver\ConfigExporterUriResolverInterface
   */
  protected $configExporterUriResolver;

  /**
   * Constructor.
   *
   * @param \Drupal\static_export\Exporter\Type\Config\ConfigExporterPluginManagerInterface $configExporterManager
   *   The config exporter manager.
   * @param \Drupal\static_export\Messenger\MessengerInterface $staticExportMessenger
   *   The static exporter messenger.
   * @param \Drupal\static_export\Exporter\Type\Config\Output\Uri\Resolver\ConfigExporterUriResolverInterface $configExporterUriResolver
   *   The config exporter path resolver.
   */
  public function __construct(ConfigExporterPluginManagerInterface $configExporterManager, MessengerInterface $staticExportMessenger, ConfigExporterUriResolverInterface $configExporterUriResolver) {
    $this->configExporterManager = $configExporterManager;
    $this->staticExportMessenger = $staticExportMessenger;
    $this->configExporterUriResolver = $configExporterUriResolver;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.static_config_exporter'),
      $container->get('static_export.messenger'),
      $container->get('static_export.config_exporter_uri_resolver')
    );
  }

  /**
   * Name of the configuration object to be deleted.
   *
   * @var string
   */
  protected $exportableConfigurationName;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'static_export_exportable_config_delete';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to delete %name?', ['%name' => $this->exportableConfigurationName]);
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    $exportUris = $this->configExporterUriResolver->setConfigName($this->exportableConfigurationName)
      ->getUris();
    $exportUrisString = '';
    foreach ($exportUris as $exportUri) {
      $exportUrisString .= '<li>' . $exportUri->getTarget() . '</li>';
    }
    return '<p>' . $this->t('This action will delete the following files, potentially triggering a build:') . '</p>' .
      new TranslatableMarkup('<ul>' . $exportUrisString . '</ul>');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('static_export.exportable_config.collection');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Delete');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, string $exportable_configuration_name = '') {
    $this->exportableConfigurationName = $exportable_configuration_name;
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\static_suite\StaticSuiteException
   * @throws \Drupal\static_suite\StaticSuiteUserException
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->configFactory()->getEditable('static_export.settings');
    $configObjects = $config->get('exportable_config.objects_to_export');
    if (($key = array_search($this->exportableConfigurationName, $configObjects, TRUE)) !== FALSE) {
      unset($configObjects[$key]);
    }
    sort($configObjects);
    $config
      ->set('exportable_config.objects_to_export', array_values($configObjects))
      ->save();

    // Tell the config exporter to delete this object data.
    $fileCollectionGroup = $this->configExporterManager->getDefaultInstance()
      ->delete(['name' => $this->exportableConfigurationName]);
    $this->staticExportMessenger->addFileCollectionGroup($fileCollectionGroup);

    $this->messenger()
      ->addMessage($this->t('The configuration %label has been deleted.', [
        '%label' => $this->exportableConfigurationName,
      ]));

    $form_state->setRedirect('static_export.exportable_config.collection');
  }

}
