<?php

namespace Drupal\static_deploy\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\static_deploy\Plugin\StaticDeployerPluginInterface;
use Drupal\static_deploy\Plugin\StaticDeployerPluginManagerInterface;
use Drupal\static_suite\Utility\SettingsUrlResolverInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configuration form for Static Deploy.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * Language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * Messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The settings URL resolver.
   *
   * @var \Drupal\static_suite\Utility\SettingsUrlResolverInterface
   */
  protected SettingsUrlResolverInterface $settingsUrlResolver;

  /**
   * The static deployer plugin manager.
   *
   * @var \Drupal\static_deploy\Plugin\StaticDeployerPluginManagerInterface
   */
  protected $staticDeployerPluginManager;

  /**
   * Constructs a \Drupal\system\ConfigFormBase object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   Language manager.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   Messenger.
   * @param \Drupal\static_suite\Utility\SettingsUrlResolverInterface $settingsUrlResolver
   *   The settings URL resolver.
   * @param \Drupal\static_deploy\Plugin\StaticDeployerPluginManagerInterface $static_deployer_manager
   *   The static deployer plugin manager.
   */
  public function __construct(ConfigFactoryInterface $config_factory, LanguageManagerInterface $language_manager, MessengerInterface $messenger, SettingsUrlResolverInterface $settingsUrlResolver, StaticDeployerPluginManagerInterface $static_deployer_manager) {
    parent::__construct($config_factory);
    $this->languageManager = $language_manager;
    $this->messenger = $messenger;
    $this->settingsUrlResolver = $settingsUrlResolver;
    $this->staticDeployerPluginManager = $static_deployer_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('language_manager'),
      $container->get('messenger'),
      $container->get('static_suite.settings_url_resolver'),
      $container->get('plugin.manager.static_deployer'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'static_deploy_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['static_deploy.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('static_deploy.settings');

    $form['deployer_container'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Select a static deployer'),
    ];

    $header = [
      'id' => $this->t('ID'),
      'name' => $this->t('Name'),
      'description' => $this->t('Description'),
      'configuration' => $this->t('Configuration'),
    ];
    $definitions = $this->staticDeployerPluginManager->getDefinitions();
    $options = [];
    foreach ($definitions as $pluginId => $pluginDefinition) {
      $option = [
        'id' => $pluginId,
        'name' => $pluginDefinition['label'],
        'description' => $pluginDefinition['description'],
      ];
      $configUrl = $this->settingsUrlResolver->setModule($pluginDefinition['provider'])
        ->resolve();
      if ($configUrl) {
        $option['configuration']['data'] = [
          '#title' => $this->t('Edit configuration'),
          '#type' => 'link',
          '#url' => $configUrl,
        ];
      }
      else {
        $option['configuration'] = $this->t('No configuration available');
      }
      $options[$pluginId] = $option;
    }

    $defaultValue = [];
    $currentDeployers = $config->get('deployers');
    if (is_array($currentDeployers)) {
      foreach ($currentDeployers as $deployer) {
        $defaultValue[$deployer] = $deployer;
      }
    }

    $form['deployer_container']['deployers'] = [
      '#type' => 'tableselect',
      '#header' => $header,
      '#options' => $options,
      '#required' => FALSE,
      '#multiple' => TRUE,
      '#prefix' =>
      '<p>' . $this->t('Any of the available deployers can be executed by running a drush command ("@drush_command"). Anyway, if you want a deploy to be automatically requested every time "Static Build" module builds your site (this is the recommended way of using this module), you should select which deployers will respond to that event.', ['@drush_command' => StaticDeployerPluginInterface::DRUSH_ASYNC_COMMAND]) .
      '<p>' . $this->t('Select the deployers that will be executed when Static Build module builds your site. Multiple deployers are allowed. If none is selected, deploying a site must be manually requested using a drush command ("@drush_command")', ['@drush_command' => StaticDeployerPluginInterface::DRUSH_ASYNC_COMMAND]) . '</p>',
      '#suffix' => $this->t('Static deployers are extensible plugins. If no one matches your needs, you can define your own deployer by creating a plugin with a @StaticDeployer annotation. Please, refer to the documentation for more info.'),
      '#empty' => $this->t('No static deployer available. Please enable a module that provides such deployer or add your own custom plugin.'),
      '#default_value' => $defaultValue,
    ];

    $form['deployer_container']['sync'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Execute synchronous deploys from web-server (not recommended)'),
      '#required' => FALSE,
      '#description' => $this->t("If a deploy is requested from a web-server process, it's asynchronously executed so it doesn't block the server's main thread. If your static deployer is fast enough to deploy your site in a couple of seconds, you may consider doing it synchronously."),
      '#default_value' => $config->get('sync'),
      '#attributes' => [
        'id' => 'sync',
      ],
    ];

    $form['advanced'] = [
      '#type' => 'details',
      '#title' => $this->t('Advanced'),
      '#open' => TRUE,
    ];

    $form['advanced']['semaphore_timeout'] = [
      '#type' => 'number',
      '#title' => $this->t('Timeout in seconds for deploy semaphores'),
      '#required' => TRUE,
      '#description' => $this->t(
        'When a deploy is running, a semaphore is kept to avoid concurrent deploys taking place. Enter the number of seconds to wait until a semaphore can be considered staled. You should enter a value high enough (e.g.- the number of seconds a deploy takes multiplied by two or three)',
      ),
      '#default_value' => $config->get('semaphore_timeout'),
    ];

    $form['advanced']['toolbar'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show deployment progress in toolbar'),
      '#required' => FALSE,
      '#default_value' => $config->get('toolbar'),
    ];

    $form['advanced']['deploy_params'] = [
      '#type' => 'details',
      '#title' => $this->t('Parameters for asynchronous deploy processes'),
      '#required' => FALSE,
      '#description' => $this->t(
        'When a deploy is asynchronously requested from a web-server, a new process is forked to run a Drush command (<code>@drush_command</code>) on a shell. You can customize the environment variables and parameters for that Drush command.',
        [
          '@drush_command' => StaticDeployerPluginInterface::DRUSH_ASYNC_COMMAND,
        ]
      ),
      '#open' => TRUE,
    ];

    $form['advanced']['deploy_params']['env'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Environment variables'),
      '#required' => FALSE,
      '#description' => $this->t('One key-value per line (i.e.- CI=true). They can only contain the following characters: letters, numbers, "$", "~", "/", "_", "-", ":", "=" and white space.'),
      '#default_value' => $config->get('env') ? implode("\n", $config->get('env')) : NULL,
    ];

    $form['advanced']['deploy_params']['drush_options'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Drush options'),
      '#required' => FALSE,
      '#description' => $this->t('Enter options like --verbose, --uri, etc. It can only contain the following characters: letters, numbers, "$", "~", "/", "_", "-", ":", "=" and white space.'),
      '#default_value' => $config->get('drush_options'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $env = $form_state->getValue('env');
    if ($env) {
      $envCharsArray = str_split($env);
      foreach ($envCharsArray as $envChar) {
        if (!preg_match('/[a-zA-Z0-9$~\/_:\-=\s]/', $envChar)) {
          $form_state->setErrorByName(
            'env',
            $this->t('Illegal character found in environment variables: "@env-char"', ['@env-char' => $envChar]));
        }
      }
    }

    $env = $this->cleanArrayInput(filter_var($form_state->getValue('env'), FILTER_SANITIZE_STRING));
    if (is_array($env)) {
      foreach ($env as $envLine) {
        if (!preg_match("/^\w+=\w+/", $envLine)) {
          $form_state->setErrorByName(
            'env',
            $this->t('Lines for environment variables must follow a key-value format, separated by "=" (i.e.- CI=TRUE)'));
        }
      }
    }

    $drushOptions = $form_state->getValue('drush_options');
    if ($drushOptions) {
      $drushOptionsCharsArray = str_split($drushOptions);
      foreach ($drushOptionsCharsArray as $drushOptionsChar) {
        if (!preg_match('/[a-zA-Z0-9$~\/_:\-=\s]/', $drushOptionsChar)) {
          $form_state->setErrorByName(
            'drush_options',
            $this->t('Illegal character found in Drush options: "@drush-options-char"', ['@drush-options-char' => $drushOptionsChar])
          );
        }
      }
    }

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('static_deploy.settings');
    $deployers = [];
    if (is_array($form_state->getValue('deployers'))) {
      $deployers = $this->cleanValueFromArray(array_values($form_state->getValue('deployers')), "0");
      $deployers = $this->cleanValueFromArray($deployers, 0);
    }
    $config
      ->set('deployers', $deployers)
      ->set('sync', $form_state->getValue('sync'))
      ->set('semaphore_timeout', $form_state->getValue('semaphore_timeout'))
      ->set('toolbar', $form_state->getValue('toolbar'))
      ->set('env', $this->cleanArrayInput(filter_var($form_state->getValue('env'), FILTER_SANITIZE_STRING)))
      ->set('drush_options', filter_var($form_state->getValue('drush_options'), FILTER_SANITIZE_STRING))
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

  /**
   * Removes an element from an array.
   *
   * @param array $array
   *   Array to be cleaned.
   * @param mixed $value
   *   Value to be cleaned.
   *
   * @return array
   *   A cleaned array.
   */
  protected function cleanValueFromArray(array $array, $value): array {
    foreach ($array as $itemKey => $itemValue) {
      if ($itemValue === $value) {
        unset($array[$itemKey]);
      }
    }
    return $array;
  }

}
