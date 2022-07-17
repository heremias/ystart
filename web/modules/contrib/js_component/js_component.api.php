<?php

/**
 * @file
 * Define the JS component API documentation.
 */

/**
 * @addtogroup hooks
 * @{
 */

/**
 * Perform altercations on the JS component form.
 *
 * @param array $form
 *   An array of JS component form elements.
 * @param array $configuration
 *   An array of JS component configurations.
 * @param \Drupal\Core\Form\FormStateInterface $form_state
 *   The form state instance.
 */
function hook_js_component_form_alter(array &$form, array $configuration, Drupal\Core\Form\FormStateInterface $form_state) {
  $form['resource'] = [
    '#type' => 'select',
    '#title' => new Drupal\Core\StringTranslation\TranslatableMarkup('Resource'),
    '#options' => [],
    '#empty_option' => new Drupal\Core\StringTranslation\TranslatableMarkup('- None -'),
    '#default_value' => $configuration['resource'] ?? NULL,
  ];
}

/**
 * React on a JS component form submit.
 *
 * @param array $values
 *   The JS component values.
 * @param \Drupal\Core\Form\FormStateInterface $form_state
 *   The form state instance.
 */
function hook_js_component_form_submit(
  array &$values,
  Drupal\Core\Form\FormStateInterface $form_state
) {
  if ($resource = $form_state->getValue('resource')) {
    $values['resource'] = $resource;
  }
}

/**
 * @} End of "addtogroup hooks".
 */
