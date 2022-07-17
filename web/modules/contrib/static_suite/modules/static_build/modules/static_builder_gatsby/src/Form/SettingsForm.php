<?php

namespace Drupal\static_builder_gatsby\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Configuration form for Static Builder Gatsby.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'static_builder_gatsby_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['static_builder_gatsby.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('static_builder_gatsby.settings');

    $form['base_dir'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Base directory'),
      '#description' => $this->t('Enter the directory inside "[BASE_DIRECTORY]/[BUILDER_ID]/[live|preview]/.build" where Gatsby should be executed (see "Directory structure for local builders" inside Static Build <a href="@url">settings page</a> for more information). Keep it empty unless using Yarn Workspaces, Lerna or similar. In such cases, a possible value could be "/packages/gatsby". It must start with a leading slash.', [
        '@url' => Url::fromRoute('static_build.settings')
          ->toString(),
      ]),
      '#default_value' => $config->get('base_dir'),
    ];

    $form['live'] = [
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => $this->t('Live mode'),
      '#description' => $this->t('Options to use when building a "live" release'),
    ];

    $form['live']['node_live_options'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Options for node process (live mode)'),
      '#description' => $this->t('Options passed to node process when building a "live" release, e.g., --max_old_space_size=4096'),
      '#required' => FALSE,
      '#default_value' => $config->get('node.live.options'),
    ];

    $form['live']['gatsby_live_options'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Options for Gatsby process (live mode)'),
      '#description' => $this->t('Options passed to Gatsby process when building a "live" release, e.g., --prefix-paths'),
      '#required' => FALSE,
      '#default_value' => $config->get('gatsby.live.options'),
    ];

    $form['preview'] = [
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => $this->t('Preview mode'),
      '#description' => $this->t('Options to use when building a "preview" release'),
    ];

    $form['preview']['node_preview_options'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Options for node process (preview mode)'),
      '#description' => $this->t('Options passed to node process when building a "preview" release, e.g., --max_old_space_size=4096'),
      '#required' => FALSE,
      '#default_value' => $config->get('node.preview.options'),
    ];

    $form['preview']['gatsby_preview_options'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Options for Gatsby process (preview mode)'),
      '#description' => $this->t('Options passed to Gatsby process when building a "preview" release, e.g., --prefix-paths'),
      '#required' => FALSE,
      '#default_value' => $config->get('gatsby.preview.options'),
    ];

    $form['advanced'] = [
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => $this->t('Advanced'),
    ];

    $form['advanced']['delete_cache'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Delete .cache folder before and after each build (not recommended)'),
      '#description' => $this->t('Useful if you experience issues due to stale cached data.'),
      '#default_value' => $config->get('delete.cache'),
    ];

    $form['advanced']['delete_public'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Delete public folder before and after each build (not recommended)'),
      '#description' => $this->t('Useful to maintain disk space under control.'),
      '#default_value' => $config->get('delete.public'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $baseDir = $form_state->getValue('base_dir');
    if (!empty($baseDir) && !str_starts_with($baseDir, '/')) {
      $form_state->setErrorByName(
        'base_dir',
        $this->t('Base directory must start with a leading slash.'));
    }

    if (!empty($baseDir) && str_contains($baseDir, '..')) {
      $form_state->setErrorByName(
        'base_dir',
        $this->t('Base directory contains illegal characters.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('static_builder_gatsby.settings');
    $config
      ->set('base_dir', $form_state->getValue('base_dir'))
      ->set('node.live.options', $form_state->getValue('node_live_options'))
      ->set('gatsby.live.options', $form_state->getValue('gatsby_live_options'))
      ->set('node.preview.options', $form_state->getValue('node_preview_options'))
      ->set('gatsby.preview.options', $form_state->getValue('gatsby_preview_options'))
      ->set('delete.cache', $form_state->getValue('delete_cache'))
      ->set('delete.public', $form_state->getValue('delete_public'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
