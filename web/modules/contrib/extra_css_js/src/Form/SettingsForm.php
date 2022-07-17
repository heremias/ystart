<?php

namespace Drupal\extra_css_js\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Defines a form that configures extra_css_js settings.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'extra_css_js';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['extra_css_js.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('extra_css_js.settings');
    $form['extra_css_js_custom_css'] = [
      '#type' => 'textarea',
      '#title' => $this->t('CSS Code'),
      '#default_value' => $config->get('extra_css_js_custom_css'),
      '#description' => $this->t('Please enter custom style without <b> @style </b> tag.', ["@style" => '<style>']) ,
    ];
    $form['extra_css_js_custom_js'] = [
      '#type' => 'textarea',
      '#title' => t('JS Code'),
      '#default_value' => $config->get('extra_css_js_custom_js'),
      '#description' => $this->t('Please enter custom script without <b> @script </b> tag.', ["@script" => '<script>']) ,
    ];
    $themes = array_keys(\Drupal::service('theme_handler')->listInfo());
    $form['extra_css_js_themes'] = [
      '#type' => 'select',
      '#multiple' => TRUE,
      '#title' => $this->t('Select Themes'),
      '#options' => array_combine($themes, $themes),
      '#default_value' => $config->get('extra_css_js_themes') ?? $themes,
      '#description' => $this->t('Select the themes, you want the CSS/JS code to appear on. If none is selected code will be applied to all the themes listed here.') ,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    extra_css_js_generate_css();
    extra_css_js_generate_js();
    // Save the configuration.
    $this->config('extra_css_js.settings')
      ->set('extra_css_js_custom_css', $form_state->getValue('extra_css_js_custom_css'))
      ->set('extra_css_js_custom_js', $form_state->getValue('extra_css_js_custom_js'))
      ->set('extra_css_js_themes', $form_state->getValue('extra_css_js_themes'))
      ->save();
    drupal_flush_all_caches();
    parent::submitForm($form, $form_state);
  }

}
