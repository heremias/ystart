<?php

namespace Drupal\static_suite\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configuration form for Static Suite.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($config_factory);
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'static_suite_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['static_suite.settings'];
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *   Thrown if the entity type doesn't exist.
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   *   Thrown if the storage handler couldn't be loaded.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('static_suite.settings');

    $form['cli'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Advanced CLI options'),
    ];

    $form['cli']['cli_allowed_users'] = [
      '#type' => 'textarea',
      '#title' => $this->t('CLI users allowed to run Static Suite operations'),
      '#required' => FALSE,
      '#description' => $this->t('Static Suite offers some drush commands and event operations that should be executed by the same user as your web server (i.e.- to avoid file permission conflicts due to exporting files by different users, or to limit who can execute some actions - like deploying a static site -). The username running this web server is "@web_server_user". Enter one username per line. If left empty, any user will be allowed to execute drush commands and event operations.<br/>Keep in mind that you can always use group permissions or ACLs to fix this potential problem and avoid file permission conflicts.', ['@web_server_user' => exec('whoami')]),
      '#default_value' => $config->get('cli_allowed_users') ? implode("\n", $config->get('cli_allowed_users')) : NULL,
    ];

    $form['cli']['cli_throw_exception_interactive_tty'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Throw an error on interactive TTYs if CLI user is not allowed to run Static Suite operations'),
      '#required' => FALSE,
      '#description' => $this->t('On interactive TTYs, running some commands like "drush cim" could trigger a export operation that could lead to an error, thus breaking the "drush cim" command. If you experience this kind of problems, disable this option.'),
      '#default_value' => $config->get('cli_throw_exception_interactive_tty'),
    ];

    $form['cli']['cli_throw_exception_non_interactive_tty'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Throw an error on non-interactive TTYs if CLI user is not allowed to run Static Suite operations (not recommended)'),
      '#required' => FALSE,
      '#description' => $this->t('On non-interactive TTYs, like cron jobs, throwing an error could lead to undesirable effects.'),
      '#default_value' => $config->get('cli_throw_exception_non_interactive_tty'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('static_suite.settings');
    $config
      ->set('cli_allowed_users', $this->cleanArrayInput($form_state->getValue('cli_allowed_users')))
      ->set('cli_throw_exception_interactive_tty', $form_state->getValue('cli_throw_exception_interactive_tty'))
      ->set('cli_throw_exception_non_interactive_tty', $form_state->getValue('cli_throw_exception_non_interactive_tty'))
      ->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * Transforms a string into an array and cleans it.
   *
   * @param string $value
   *   A string value with line breaks.
   *
   * @return array
   *   An array obtained from the given string.
   */
  protected function cleanArrayInput(string $value): array {
    $array = explode("\n", $value);
    $array = array_map('trim', $array);
    return array_filter($array);
  }

}
