<?php

namespace Drupal\js_component;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Define JS component base form.
 */
abstract class JSComponentBaseForm implements JSComponentFormInterface, JsComponentInjectContainerInterface {

  use StringTranslationTrait;
  use DependencySerializationTrait;

  /**
   * @var array
   */
  protected $configuration = [];

  /**
   * The constructor for the JS component base form.
   *
   * @param array $configuration
   *   An array of component configurations.
   */
  public function __construct(array $configuration) {
    $this->configuration = $configuration;
  }

  /**
   * {@inheritDoc}
   */
  public static function create(array $configuration, ContainerInterface $container) {
    return new static ($configuration);
  }

  /**
   * {@inheritdoc}
   */
  public function buildComponentForm(array $form, FormStateInterface $form_state) {
    $form['#attributes']['id'] = $this->buildAjaxWrapperId($form);
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateComponentForm(array $form, FormStateInterface $form_state) {}

  /**
   * {@inheritDoc}
   */
  public function submitComponentForm(array $form, FormStateInterface $form_state) {
    $this->configuration = $form_state->cleanValues()->getValues();
  }

  /**
   * {@inheritDoc}
   */
  public function getConfiguration() {
    return $this->configuration + $this->defaultConfiguration();
  }

  /**
   * Build the ajax callback.
   *
   * @param array $form
   *   An array of form elements.
   *
   * @return array
   *   The form ajax callback implementation.
   */
  protected function buildAjaxCallback(array $form) {
    return [
      'wrapper' => $this->buildAjaxWrapperId($form),
      'callback' => [$this, 'callAjaxCallback'],
    ];
  }

  /**
   * The ajax callback for the component form.
   *
   * @param array $form
   *   An array of form elements.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state instance.
   *
   * @return array
   *   An array of the subset of form elements.
   */
  public function callAjaxCallback(array $form, FormStateInterface $form_state) {
    $element = $form_state->getTriggeringElement();
    $parents = array_slice(
      $element['#array_parents'], 0, -1
    );

    return NestedArray::getValue($form, $parents);
  }

  /**
   * Build the ajax wrapper identifier.
   *
   * @param array $form
   *   The form array elements.
   *
   * @return string
   *   The form ajax wrapper identifier.
   */
  protected function buildAjaxWrapperId(array $form) {
    $wrapper_id = ['js-component'];

    if (isset($form['#parents']) && !empty($form['#parents'])) {
      $wrapper_id[] = implode('-', $form['#parents']);
    }

    return Html::getId(implode('-', $wrapper_id));
  }

  /**
   * Get the form state value with configuration fallback.
   *
   * @param $name
   *   The form element name.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state instance.
   * @param null $default_value
   *   The default value to use if no value is retrieved.
   * @param array $parents
   *
   * @return mixed|null
   *   Return the form state value.
   */
  protected function getFormStateValue(
    $name,
    FormStateInterface $form_state,
    $default_value = NULL,
    array $parents = []
  ) {
    if (!is_array($name)) {
      $name = [$name];
    }
    $user_input = NestedArray::getValue($form_state->getUserInput(), $parents)
      ?? [];

    foreach ([$form_state->getValues(), $user_input, $this->getConfiguration()] as $input) {
      $exist = NULL;
      $value = NestedArray::getValue($input, $name, $exist);
      if (empty($value) && !$exist) {
        continue;
      }
      return $value;
    }

    return $default_value;
  }

  /**
   * Define the default component configuration.
   *
   * @return array
   *   An array of default configuration.
   */
  protected function defaultConfiguration() {
    return [];
  }
}
