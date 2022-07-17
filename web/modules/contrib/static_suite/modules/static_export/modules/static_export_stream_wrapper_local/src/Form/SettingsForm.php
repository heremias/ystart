<?php

namespace Drupal\static_export_stream_wrapper_local\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configuration form for static-local stream wrapper.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'static_export_stream_wrapper_local_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['static_export_stream_wrapper_local.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('static_export_stream_wrapper_local.settings');

    $form['data_dir'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Data directory path'),
      '#required' => TRUE,
      '#description' => $this->t('Exported data is saved in the above path. It must start with a leading slash. It should be writable by the user running your web-server (usually <em>www-data</em> or similar). Relative to <em>DRUPAL_ROOT</em> (@drupal_root). It can be set outside <em>DRUPAL_ROOT</em> using "../"', ['@drupal_root' => DRUPAL_ROOT]),
      '#default_value' => $config->get('data_dir'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $dataDir = $form_state->getValue('data_dir');
    if (strpos($dataDir, '/') !== 0) {
      $form_state->setErrorByName(
        'data_dir',
        $this->t('Data directory path must start with a leading slash.'));
    }

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('static_export_stream_wrapper_local.settings');
    $config
      ->set('data_dir', rtrim($form_state->getValue('data_dir'), '/'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
