<?php

namespace Drupal\cl_components\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure Storybook Components settings for this site.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'cl_components_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['cl_components.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $paths = $this->config('cl_components.settings')->get('paths');
    $form['paths'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Paths'),
      '#rows' => 4,
      '#description' => $this->t('Locations in the filesystem where to scan for components. Please consider providing a location with assets already compiled. Enter one path per line relative to the Drupal root.'),
      '#default_value' => implode("\n", $paths),
    ];
    $form['debug'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Debug'),
      '#description' => $this->t('Use additional debugging tools for components. This is in addition to the debug HTML comments added to the DOM when setting <code>twig.config.debug: true</code> in your development.services.yml container.'),
      '#default_value' => $this->config('cl_components.settings')->get('debug'),
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $paths = $this->massagePathsValue($form_state->getValue('paths'));
    $debug = (bool) $form_state->getValue('debug');
    $this->config('cl_components.settings')
      ->set('paths', $paths)
      ->set('debug', $debug)
      ->save();
    parent::submitForm($form, $form_state);
  }

  /**
   * Massages the text in the text area into a list of paths.
   *
   * @param string $paths_string
   *   The text area contents.
   *
   * @return array
   *   The paths to process.
   */
  private function massagePathsValue(string $paths_string): array {
    $lines = explode("\n", $paths_string);
    $lines = array_map('trim', $lines);
    return array_filter($lines, 'strlen');
  }

}
