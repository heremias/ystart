<?php

namespace Drupal\js_component;

use Drupal\Core\Form\FormStateInterface;

/**
 * Define a JS component form interface.
 */
interface JSComponentFormInterface {

  /**
   * Get the component form configurations.
   *
   * @return array
   *   An array of component configurations.
   */
  public function getConfiguration();

  /**
   * Validate the component form.
   *
   * @param array $form
   *   An array of form elements.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state instance.
   */
  public function validateComponentForm(array $form, FormStateInterface $form_state);

  /**
   * Build the component form.
   *
   * @param array $form
   *   An array of form elements.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state instance.
   */
  public function buildComponentForm(array $form, FormStateInterface $form_state);

  /**
   * Submit the component form.
   *
   * @param array $form
   *   An array of form elements.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state instance.
   */
  public function submitComponentForm(array $form, FormStateInterface $form_state);
}
