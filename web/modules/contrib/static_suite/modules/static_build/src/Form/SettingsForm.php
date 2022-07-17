<?php

namespace Drupal\static_build\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Render\Markup;
use Drupal\static_build\Plugin\StaticBuilderPluginInterface;
use Drupal\static_build\Plugin\StaticBuilderPluginManagerInterface;
use Drupal\static_suite\Utility\SettingsUrlResolverInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configuration form for Static Build.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

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
   * The static builder plugin manager.
   *
   * @var \Drupal\static_build\Plugin\StaticBuilderPluginManagerInterface
   */
  protected $staticBuilderPluginManager;

  /**
   * Constructs a \Drupal\system\ConfigFormBase object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   Messenger.
   * @param \Drupal\static_suite\Utility\SettingsUrlResolverInterface $settingsUrlResolver
   *   The settings URL resolver.
   * @param \Drupal\static_build\Plugin\StaticBuilderPluginManagerInterface $static_builder_manager
   *   The static builder plugin manager.
   */
  public function __construct(ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_type_manager, MessengerInterface $messenger, SettingsUrlResolverInterface $settingsUrlResolver, StaticBuilderPluginManagerInterface $static_builder_manager) {
    parent::__construct($config_factory);
    $this->entityTypeManager = $entity_type_manager;
    $this->messenger = $messenger;
    $this->settingsUrlResolver = $settingsUrlResolver;
    $this->staticBuilderPluginManager = $static_builder_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('entity_type.manager'),
      $container->get('messenger'),
      $container->get('static_suite.settings_url_resolver'),
      $container->get('plugin.manager.static_builder'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'static_build_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['static_build.settings'];
  }

  /**
   * Creates a builder's list for a form.
   *
   * @param string $runMode
   *   Run mode, live or preview.
   *
   * @return array
   *   Array of data prepared for a form.
   */
  public function getBuilderListFormElement(string $runMode): array {
    $config = $this->config('static_build.settings');

    $form['builder_container'] = [
      '#markup' => '<p>' . $this->t('Select the builders to execute in "%runMode" mode when Static Export module exports data. Multiple builders are allowed. If none is selected, building a site must be manually requested using a drush command ("@drush_command")', [
        '%runMode' => $runMode,
        '@drush_command' => StaticBuilderPluginInterface::DRUSH_ASYNC_COMMAND . ' [builder] ' . $runMode,
      ]) . '</p>',
    ];

    $header = [
      'id' => $this->t('ID'),
      'name' => $this->t('Name'),
      'description' => $this->t('Description'),
      'type' => $this->t('Type'),
      'configuration' => $this->t('Configuration'),
    ];
    $definitions = $this->staticBuilderPluginManager->getDefinitions();
    $options = [];
    foreach ($definitions as $pluginId => $pluginDefinition) {
      $option = [
        'id' => $pluginId,
        'name' => $pluginDefinition['label'],
        'description' => $pluginDefinition['description'],
        'type' => $pluginDefinition['host'],
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
    $currentBuilders = $config->get($runMode . '.builders');
    if (is_array($currentBuilders)) {
      foreach ($currentBuilders as $builder) {
        $defaultValue[$builder] = $builder;
      }
    }

    $form['builder_container'][$runMode . '_builders'] = [
      '#type' => 'tableselect',
      '#header' => $header,
      '#options' => $options,
      '#required' => FALSE,
      '#multiple' => TRUE,
      '#empty' => $this->t('No static builder available. Please enable a module that provides such builder or add your own custom plugin.'),
      '#default_value' => $defaultValue,
    ];

    return $form['builder_container'];
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *   Thrown if the entity type doesn't exist.
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   *   Thrown if the storage handler couldn't be loaded.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('static_build.settings');

    if ($this->getRequest()
      ->getMethod() === 'GET' && $config->get('base_dir')) {
      $errorGroups = [];
      $localDefinitions = $this->staticBuilderPluginManager->getLocalDefinitions();
      $cloudDefinitions = $this->staticBuilderPluginManager->getCloudDefinitions();
      if (is_array($localDefinitions)) {
        foreach (array_keys($localDefinitions) as $localBuilderId) {
          $localStaticBuilderLiveErrors = $this->staticBuilderPluginManager->validateAllDirsStructure(
            $localBuilderId,
            ['run-mode' => StaticBuilderPluginInterface::RUN_MODE_LIVE],
          );
          if (count($localStaticBuilderLiveErrors) > 0) {
            $errorGroups[] = $localStaticBuilderLiveErrors;
          }
          $localStaticBuilderPreviewErrors = $this->staticBuilderPluginManager->validateAllDirsStructure(
            $localBuilderId,
            ['run-mode' => StaticBuilderPluginInterface::RUN_MODE_PREVIEW],
          );
          if (count($localStaticBuilderPreviewErrors) > 0) {
            $errorGroups[] = $localStaticBuilderPreviewErrors;
          }
        }
      }
      if (is_array($cloudDefinitions)) {
        foreach (array_keys($cloudDefinitions) as $cloudBuilderId) {
          $cloudStaticBuilderLiveErrors = $this->staticBuilderPluginManager->validateAllDirsStructure(
            $cloudBuilderId,
            ['run-mode' => StaticBuilderPluginInterface::RUN_MODE_LIVE],
          );
          if (count($cloudStaticBuilderLiveErrors) > 0) {
            $errorGroups[] = $cloudStaticBuilderLiveErrors;
          }
        }
      }

      if (count($errorGroups) > 0) {
        $translatedErrors = [];
        foreach ($errorGroups as $errorGroup) {
          foreach ($errorGroup as $error) {
            $translatedErrors[] = $error;
          }
        }
        $this->messenger->addError(Markup::create($this->t('Based on your current configuration, we found these errors:') . '<ul><li>' . implode('</li><li>', $translatedErrors) . '</li></ul>'));
      }
    }

    $form['builder_container'] = [
      '#markup' =>
      '<p>' . $this->t('Building a site can happen in two different places: in your own "<strong>local</strong>" server, or in a CI/CD service in the "<strong>cloud</strong>". This module supports both scenarios, thanks to what is known as <strong>local builders</strong> and <strong>cloud builders</strong>.') . '</p>' .
      '<p>' . $this->t('Any of the available builders can be executed by running a drush command ("@drush_command"). Anyway, if you want a build to be automatically requested every time "Static Export" module exports data (this is the recommended way of using this module), you should select which builders will respond to that event.', ['@drush_command' => StaticBuilderPluginInterface::DRUSH_ASYNC_COMMAND]) .
      '<p>' . $this->t('Static builders are extensible plugins. If no one matches your needs, you can define your own builder by creating a plugin with a "@annotation" annotation. Please, refer to the documentation for more info.', ['@annotation' => '@StaticBuilder']) . '</p>',
    ];
    $form['builder_container'][StaticBuilderPluginInterface::RUN_MODE_LIVE] = [
      '#type' => 'details',
      '#title' => $this->t('Builders to execute in "@run_mode" mode', ['@run_mode' => StaticBuilderPluginInterface::RUN_MODE_LIVE]),
      '#open' => TRUE,
    ];
    $form['builder_container'][StaticBuilderPluginInterface::RUN_MODE_LIVE]['builders'] = $this->getBuilderListFormElement(StaticBuilderPluginInterface::RUN_MODE_LIVE);

    $form['builder_container'][StaticBuilderPluginInterface::RUN_MODE_PREVIEW] = [
      '#type' => 'details',
      '#title' => $this->t('Builders to execute in "@run_mode" mode', ['@run_mode' => StaticBuilderPluginInterface::RUN_MODE_PREVIEW]),
      '#open' => TRUE,
    ];
    $form['builder_container'][StaticBuilderPluginInterface::RUN_MODE_PREVIEW]['builders'] = $this->getBuilderListFormElement(StaticBuilderPluginInterface::RUN_MODE_PREVIEW);

    $form['builder_container'][StaticBuilderPluginInterface::HOST_MODE_LOCAL][StaticBuilderPluginInterface::HOST_MODE_LOCAL . '_sync'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Execute local builds synchronously from web-server (not recommended)'),
      '#required' => FALSE,
      '#description' => $this->t("If a local build is requested from a web-server process, it's asynchronously executed so it doesn't block the server's main thread. If your static local builder is fast enough to build your site in a couple of seconds, you may consider doing it synchronously.<br/>There is no such option for static cloud builders, and all of them must run detached from the web-server process."),
      '#default_value' => $config->get(StaticBuilderPluginInterface::HOST_MODE_LOCAL . '.sync'),
    ];

    $form['base_dir_container'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Build base directory'),
    ];

    $form['base_dir_container']['base_dir'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Base directory for site building'),
      '#required' => TRUE,
      '#description' => $this->t('This module will use the above path to build and create a release of this site.<br/>It must start with a leading slash and be relative to <em>DRUPAL_ROOT</em> (@drupal_root).<br/>It can be set outside <em>DRUPAL_ROOT</em> using "../"', ['@drupal_root' => DRUPAL_ROOT]),
      '#default_value' => $config->get('base_dir'),
    ];

    $form['base_dir_container']['dir_info'] = [
      '#type' => 'details',
      '#title' => $this->t('IMPORTANT: you *MUST* create the following directory structure'),
      '#open' => TRUE,
      '#markup' =>
      '<p>' . $this->t('Follow these steps:') . '</p>',
    ];

    $form['base_dir_container']['dir_info']['dir_info_local'] = [
      '#type' => 'details',
      '#title' => $this->t('Directory structure for local builders'),
      '#open' => FALSE,
      '#markup' =>
      '<pre>
├── [BASE_DIRECTORY] <em><small>(' . $this->t('You must create it. Not writable by web-server') . ')</small></em>
│   ├── [BUILDER_ID]  <em><small>' . $this->t('(You must create it. Not writable by web-server)') . '</small></em>
│   │   ├── live  <em><small>' . $this->t('(You must create it. Must be writable by web-server)') . '</small></em>
│   │   │   ├── .build  <em><small>(' . $this->t('You must create it and provide the contents for the SSG -Static Site Generator- of your choice') . ')</small></em>
│   │   │   ├── current ---> releases/[RELEASE_ID]  <em><small>(' . $this->t('Created by this module') . ')</small></em>
│   │   │   ├── releases/  <em><small>(' . $this->t('Created by this module') . ')</small></em>
│   │   │   │   ├── [RELEASE_1]  <em><small>(' . $this->t('Created by this module') . ')</small></em>
│   │   │   │   ├── [RELEASE_2]  <em><small>(' . $this->t('Created by this module') . ')</small></em>
│   │   │   │   ├── [RELEASE_N]  <em><small>(' . $this->t('Created by this module') . ')</small></em>
│   │   ├── preview  <em><small>(' . $this->t('You must create it. Must be writable by web-server') . ')</small></em>
│   │   │   ├── .build  <em><small>(' . $this->t('You must create it and provide the contents for the SSG -Static Site Generator- of your choice') . ')</small></em>
│   │   │   ├── current ---> releases/[RELEASE_ID]  <em><small>(' . $this->t('Created by this module') . ')</small></em>
│   │   │   ├── releases/  <em><small>(' . $this->t('Created by this module') . ')</small></em>
│   │   │   │   ├── [RELEASE_1]  <em><small>(' . $this->t('Created by this module') . ')</small></em>
│   │   │   │   ├── [RELEASE_2]  <em><small>(' . $this->t('Created by this module') . ')</small></em>
│   │   │   │   ├── [RELEASE_N]  <em><small>(' . $this->t('Created by this module') . ')</small></em>
</pre>' .
      '<p>' . $this->t(
          'For "@local" builds, this module will execute the "local static builders" of your choice and will place their artifacts inside a <code>@release_dir</code> folder. It will also make a symlink to the newly created release, pointing from <code>@current</code> to <code>@release_dir</code>. If you want to serve the contents of your static site, you can use that "current" symlink as the DOCUMENT_ROOT for your virtual host.',
          [
            '@local' => StaticBuilderPluginInterface::HOST_MODE_LOCAL,
            '@release_dir' => '[BASE_DIRECTORY]/[BUILDER_ID]/[live|preview]/releases/[RELEASE_ID]',
            '@current' => '[BASE_DIRECTORY]/[BUILDER_ID]/[live|preview]/current',
          ]
      ) . '</p>',
    ];

    $form['base_dir_container']['dir_info']['dir_info_cloud'] = [
      '#type' => 'details',
      '#title' => $this->t('Directory structure for cloud builders'),
      '#open' => FALSE,
      '#markup' =>
      '<pre>
├── [BASE_DIRECTORY] <em><small>(' . $this->t('You must create it. Not writable by web-server)') . '</small></em>
│   ├── [BUILDER_ID]  <em><small>' . $this->t('(You must create it. Not writable by web-server)') . '</small></em>
│   │   ├── live  <em><small>' . $this->t('(You must create it. Must be writable by web-server)') . '</small></em>
│   │   │   ├── .build  <em><small>(' . $this->t('Created by this module. No need to provide any content for this folder') . ')</small></em>
│   │   │   ├── current ---> releases/[RELEASE_ID]  <em><small>(' . $this->t('Created by this module') . ')</small></em>
│   │   │   ├── releases/  <em><small>(' . $this->t('Created by this module') . ')</small></em>
│   │   │   │   ├── [RELEASE_1]  <em><small>(' . $this->t('Created by this module') . ')</small></em>
│   │   │   │   ├── [RELEASE_2]  <em><small>(' . $this->t('Created by this module') . ')</small></em>
│   │   │   │   ├── [RELEASE_N]  <em><small>(' . $this->t('Created by this module') . ')</small></em>
</pre>' .
      '<p>' . $this->t(
          'For "@cloud" builds, this module will execute the "cloud static builders" of your choice and will place the data needed by their CI/CD services inside <code>@build_dir</code>. It will also make a symlink to the newly created release, pointing from <code>@current</code> to <code>@release_dir</code>.',
          [
            '@cloud' => StaticBuilderPluginInterface::HOST_MODE_CLOUD,
            '@build_dir' => '[BASE_DIRECTORY]/[BUILDER_ID]/live/.build',
            '@current' => '[BASE_DIRECTORY]/[BUILDER_ID]/live/current',
          ]
      ) . '</p>',
    ];

    $form['base_dir_container']['number_of_releases_to_keep'] = [
      '#type' => 'number',
      '#title' => $this->t('Number of releases to keep'),
      '#required' => TRUE,
      '#description' => $this->t(
        'When a new release of your site is built, old ones are kept inside <code>@release_dir</code>. You should keep a small amount of releases. A value between 2 and 5 is a wise choice.',
        [
          '@release_dir' => '[BASE_DIRECTORY]/[BUILDER_ID]/[live|preview]/releases/',
        ]
      ),
      '#default_value' => $config->get('number_of_releases_to_keep'),
    ];

    $form['advanced'] = [
      '#type' => 'details',
      '#title' => $this->t('Advanced'),
      '#open' => TRUE,
    ];

    $form['advanced']['semaphore_timeout'] = [
      '#type' => 'number',
      '#title' => $this->t('Timeout in seconds for build semaphores'),
      '#required' => TRUE,
      '#description' => $this->t(
        'When a build is running, a semaphore is kept to avoid concurrent builds taking place. Enter the number of seconds to wait until a semaphore can be considered staled. You should enter a value high enough (e.g.- the number of seconds a build takes multiplied by two or three)',
      ),
      '#default_value' => $config->get('semaphore_timeout'),
    ];

    $form['advanced']['toolbar'] = [
      '#type' => 'details',
      '#title' => $this->t('Show build progress in toolbar'),
      '#open' => TRUE,
    ];

    $form['advanced']['toolbar']['live_toolbar'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enabled for "live" mode'),
      '#required' => FALSE,
      '#default_value' => $config->get('live.toolbar'),
    ];

    $form['advanced']['toolbar']['preview_toolbar'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enabled for "preview" mode'),
      '#required' => FALSE,
      '#default_value' => $config->get('preview.toolbar'),
    ];

    $form['advanced']['build_params'] = [
      '#type' => 'details',
      '#title' => $this->t('Parameters for asynchronous build processes'),
      '#required' => FALSE,
      '#description' => $this->t(
        'When a build is asynchronously requested from a web-server, a new process is forked to run a Drush command (<code>@drush_command</code>) on a shell. You can customize the environment variables and parameters for that Drush command.',
        [
          '@drush_command' => StaticBuilderPluginInterface::DRUSH_ASYNC_COMMAND,
        ]
      ),
      '#open' => TRUE,
    ];

    $form['advanced']['build_params']['env'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Environment variables'),
      '#required' => FALSE,
      '#description' => $this->t('One key-value per line (i.e.- CI=true). They can only contain the following characters: letters, numbers, "$", "~", "/", "_", "-", ":", "=" and white space.'),
      '#default_value' => $config->get('env') ? implode("\n", $config->get('env')) : NULL,
    ];

    $form['advanced']['build_params']['drush_options'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Drush options'),
      '#required' => FALSE,
      '#description' => $this->t('Enter options like --verbose, --uri, etc. It can only contain the following characters: letters, numbers, "$", "~", "/", "_", "-", ":", "=" and white space.'),
      '#default_value' => $config->get('drush_options'),
    ];

    $form['advanced']['build_trigger_regexp_list'] = [
      '#type' => 'details',
      '#title' => $this->t('Triggering a build: regular expressions for changed files'),
      '#description' => $this->t('When a build is requested, it looks for files that were changed since last build. If any file has changed, build is triggered. These regular expressions allow you to tailor this logic to your needs, defining which files should trigger a build.'),
      '#open' => TRUE,
    ];

    $form['advanced']['build_trigger_regexp_list']['build_trigger_regexp_list_live'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Regular Expressions to detect changed files that trigger a "@live" build', ['@live' => StaticBuilderPluginInterface::RUN_MODE_LIVE]),
      '#required' => FALSE,
      '#description' => $this->t(
        'One regular expression per line. In most cases, it should be left empty, so all build requests trigger a build when any file changes.',
        [
          '@live' => StaticBuilderPluginInterface::RUN_MODE_LIVE,
        ]),
      '#default_value' => $config->get('live.build_trigger_regexp_list') ? implode("\n", $config->get('live.build_trigger_regexp_list')) : NULL,
    ];

    $form['advanced']['build_trigger_regexp_list']['build_trigger_regexp_list_preview'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Regular Expressions to detect changed files that trigger a "@preview" build', ['@preview' => StaticBuilderPluginInterface::RUN_MODE_PREVIEW]),
      '#required' => FALSE,
      '#description' => $this->t("One regular expression per line. In some cases, it can be left empty, but there are some preview systems that could make use of this field."),
      '#default_value' => $config->get('preview.build_trigger_regexp_list') ? implode("\n", $config->get('preview.build_trigger_regexp_list')) : NULL,
    ];

    $nodeTypes = $this->entityTypeManager->getStorage('node_type')
      ->loadMultiple();
    $nodeTypesOptions = [];
    foreach ($nodeTypes as $nodeType) {
      $nodeTypesOptions[$nodeType->id()] = $nodeType->label();
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $baseDir = $form_state->getValue('base_dir');
    if (strpos($baseDir, '/') !== 0) {
      $form_state->setErrorByName(
        'base_dir',
        $this->t('Build base directory must start with a leading slash.'));
    }

    if ($form_state->getValue('number_of_releases_to_keep') < 1) {
      $form_state->setErrorByName(
        'number_of_releases_to_keep',
        $this->t('Number of releases to keep must be greater than 0.'));
    }

    $env = $form_state->getValue('env');
    if ($env) {
      $envCharsArray = str_split($env);
      foreach ($envCharsArray as $envChar) {
        if (!preg_match('/[a-zA-Z0-9$~\/_:\-=\s]/', $envChar)) {
          $form_state->setErrorByName(
            'env',
            $this->t('Illegal character found in environment variables: "@char"', ['@char' => $envChar]));
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
            $this->t('Illegal character found in Drush options: "@char"', ['@char' => $drushOptionsChar]));
        }
      }
    }

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('static_build.settings');
    $liveBuilders = [];
    if (is_array($form_state->getValue('live_builders'))) {
      $liveBuilders = $this->cleanValueFromArray(array_values($form_state->getValue('live_builders')), '0');
      $liveBuilders = $this->cleanValueFromArray($liveBuilders, 0);
    }
    $previewBuilders = [];
    if (is_array($form_state->getValue('preview_builders'))) {
      $previewBuilders = $this->cleanValueFromArray(array_values($form_state->getValue('preview_builders')), '0');
      $previewBuilders = $this->cleanValueFromArray($previewBuilders, 0);
    }
    $config
      ->set('live.builders', $liveBuilders)
      ->set('live.request_deploy', $form_state->getValue('live_request_deploy'))
      ->set('live.build_trigger_regexp_list', $this->cleanArrayInput($form_state->getValue('build_trigger_regexp_list_live')))
      ->set('live.toolbar', $form_state->getValue('live_toolbar'))
      ->set('preview.builders', $previewBuilders)
      ->set('preview.request_deploy', $form_state->getValue('preview_request_deploy'))
      ->set('preview.build_trigger_regexp_list', $this->cleanArrayInput($form_state->getValue('build_trigger_regexp_list_preview')))
      ->set('preview.toolbar', $form_state->getValue('preview_toolbar'))
      ->set('local.sync', $form_state->getValue('local_sync'))
      ->set('base_dir', $form_state->getValue('base_dir'))
      ->set('number_of_releases_to_keep', $form_state->getValue('number_of_releases_to_keep'))
      ->set('semaphore_timeout', $form_state->getValue('semaphore_timeout'))
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
