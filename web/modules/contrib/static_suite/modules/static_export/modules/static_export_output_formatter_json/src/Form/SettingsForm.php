<?php

namespace Drupal\static_export_output_formatter_json\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configuration form for JSON output formatter.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'static_export_output_formatter_json_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['static_export_output_formatter_json.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('static_export_output_formatter_json.settings');

    $form['pretty_print'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Pretty-print JSON data?'),
      '#required' => FALSE,
      '#description' => $this->t('Not recommended unless using the Git stream wrapper or a similar one where line diffing is important. Pretty-printing JSON data will make your files take more space on disk, and be slightly slower when parsed.'),
      '#default_value' => $config->get('pretty_print'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('static_export_output_formatter_json.settings');
    $config
      ->set('pretty_print', (bool) $form_state->getValue('pretty_print'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
