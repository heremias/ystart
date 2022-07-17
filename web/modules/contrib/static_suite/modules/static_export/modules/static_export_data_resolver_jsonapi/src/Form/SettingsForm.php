<?php

namespace Drupal\static_export_data_resolver_jsonapi\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configuration form for JSON:API Data Resolver.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'static_export_data_resolver_jsonapi_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['static_export_data_resolver_jsonapi.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('static_export_data_resolver_jsonapi.settings');

    $form['endpoint'] = [
      '#type' => 'textfield',
      '#prefix' => $this->t('JSON:API data resolver obtains its data by executing an internal request to a JSON:API endpoint. Enter the path to that endpoint.'),
      '#title' => $this->t('Path to JSON:API endpoint'),
      '#required' => TRUE,
      '#description' => $this->t('Path to the JSON:API endpoint on your Drupal installation (usually "%jsonapi_path"). It must start with a leading slash.', ['%jsonapi_path' => '/jsonapi']),
      '#default_value' => $config->get('endpoint'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $path = $form_state->getValue('endpoint');
    if (strpos($path, '/') !== 0) {
      $form_state->setErrorByName(
        'endpoint',
        $this->t('Path for JSON:API endpoint must start with a leading slash.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('static_export_data_resolver_jsonapi.settings');
    $config
      ->set('endpoint', rtrim($form_state->getValue('endpoint'), '/'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
