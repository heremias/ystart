<?php

namespace Drupal\static_export\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StreamWrapper\StreamWrapperInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Drupal\Core\Url;
use Drupal\static_suite\Utility\SettingsUrlResolverInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configuration form for Static Export.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The stream wrapper manager.
   *
   * @var \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface
   */
  protected $streamWrapperManager;

  /**
   * The route provider.
   *
   * @var \Drupal\Core\Routing\RouteProviderInterface
   */
  protected $routeProvider;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The info parser service.
   *
   * @var \Drupal\Core\Extension\InfoParserInterface
   */
  protected $infoParser;

  /**
   * The settings URL resolver.
   *
   * @var \Drupal\static_suite\Utility\SettingsUrlResolverInterface
   */
  protected SettingsUrlResolverInterface $settingsUrlResolver;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface $streamWrapperManager
   *   The stream wrapper manager.
   * @param \Drupal\static_suite\Utility\SettingsUrlResolverInterface $settingsUrlResolver
   *   The settings URL resolver.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    EntityTypeManagerInterface $entity_type_manager,
    StreamWrapperManagerInterface $streamWrapperManager,
    SettingsUrlResolverInterface $settingsUrlResolver) {
    parent::__construct($config_factory);
    $this->entityTypeManager = $entity_type_manager;
    $this->streamWrapperManager = $streamWrapperManager;
    $this->settingsUrlResolver = $settingsUrlResolver;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('entity_type.manager'),
      $container->get('stream_wrapper_manager'),
      $container->get('static_suite.settings_url_resolver'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'static_export_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['static_export.settings'];
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
    $config = $this->config('static_export.settings');

    $streamWrappers = $this->streamWrapperManager->getWrappers(StreamWrapperInterface::WRITE_VISIBLE);
    ksort($streamWrappers);
    $header = [
      'id' => $this->t('Scheme'),
      'name' => $this->t('Name'),
      'description' => $this->t('Description'),
      'class' => $this->t('Provider'),
      'type' => $this->t('Storage'),
      'configuration' => $this->t('Configuration'),
    ];
    $options = NULL;
    foreach ($streamWrappers as $scheme => $streamWrapperData) {
      $streamWrapper = $this->streamWrapperManager->getViaScheme($scheme);
      $row = [
        'id' => $scheme,
        'name' => $streamWrapper->getName(),
        'description' => $streamWrapper->getDescription(),
        'class' => $streamWrapperData['class'],
        'type' => $streamWrapper::getType() === StreamWrapperInterface::LOCAL_NORMAL ? $this->t('local') : $this->t('remote'),
      ];

      $configUrl = NULL;
      if (in_array($scheme, ['public', 'private'])) {
        $configUrl = Url::fromRoute('system.file_system_settings');
      }
      else {
        $configUrl = $this->settingsUrlResolver->setRoutePrefix('static_export.stream_wrapper.')
          ->setRouteKey($scheme)
          ->setClass($row['class'])
          ->resolve();
      }

      // Add $configUrl to $row.
      if ($configUrl) {
        $row['configuration']['data'] = [
          '#title' => $this->t('Edit configuration'),
          '#type' => 'link',
          '#url' => $configUrl,
        ];
      }
      else {
        $row['configuration']['data'] = $this->t('No configuration available');
      }

      $options[$scheme] = $row;
    }

    $form['stream_wrapper_container'] = [
      '#type' => 'details',
      '#title' => $this->t('Select a writable stream wrapper to store the output of export operations'),
      '#description' => '<p>' . $this->t(
          'Any writable stream wrapper can be used to store the output of export operations. All available stream wrappers are listed below. If no one matches your needs, you can register your own using PHP "@function" function.', ['@function' => 'stream_wrapper_register()']
      ) . '</p>',
      '#open' => TRUE,
    ];

    $form['stream_wrapper_container']['stream_wrapper'] = [
      '#type' => 'tableselect',
      '#title' => $this->t('Stream wrapper'),
      '#header' => $header,
      '#options' => $options,
      '#required' => TRUE,
      '#multiple' => FALSE,
      '#empty' => $this->t('No stream wrapper available.'),
      '#description' => $this->t('Exported data is stored using a stream wrapper, which allows to easily change where and how is data stored. Static Export does not enforce any configuration on any stream wrapper, so they should be already configured to be work seamlessly.'),
      '#default_value' => $config->get('uri.scheme'),
    ];

    $missingStreamWrappers = [];
    if (empty($options['static-local'])) {
      $missingStreamWrappers[] = 'Local file system -static_export_stream_wrapper_local-';
    }
    if (empty($options['static-git'])) {
      $missingStreamWrappers[] = 'Git file system -static_export_stream_wrapper_git-';
    }
    if (count($missingStreamWrappers)) {
      $form['stream_wrapper_container']['streamer_missing_info'] = [
        '#markup' => '<p>' . $this->t('Please note that <strong>Static Export provides other stream wrappers as modules than you may consider enabling</strong> (' . implode(', ', $missingStreamWrappers) . ').') . '</p>',
      ];
    }

    $streamWrapper = $this->streamWrapperManager->getViaScheme($config->get('uri.scheme'));
    if ($streamWrapper::getType() === StreamWrapperInterface::LOCAL_NORMAL) {
      $form['stream_wrapper_container']['download_data'] = [
        '#title' => $this->t('Download exported data'),
        '#type' => 'link',
        '#url' => Url::fromRoute('static_export.data_download', ['scheme' => $config->get('uri.scheme')]),
      ];
    }

    $form['work'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Work directory'),
    ];

    $form['work']['work_dir'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Work directory path'),
      '#required' => TRUE,
      '#description' => $this->t('A special directory is used to manage, among others, the queue and logs of export operations. It must start with a leading slash. It should be writable by the user running your web-server (usually <em>www-data</em> or similar). Relative to <em>DRUPAL_ROOT</em> (@drupal_root). It can be set outside <em>DRUPAL_ROOT</em> using "../". If using "static-local" stream wrapper, a recommended value is the value from its "Data directory path", plus ".work".', ['@drupal_root' => DRUPAL_ROOT]),
      '#default_value' => $config->get('work_dir'),
    ];

    $form['work']['max_days_to_keep_logs'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum days to keep log files'),
      '#required' => TRUE,
      '#description' => $this->t('Old logs will be automatically deleted when Drupal cron runs.'),
      '#default_value' => $config->get('max_days_to_keep_logs'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $workDir = $form_state->getValue('work_dir');
    if (strpos($workDir, '/') !== 0) {
      $form_state->setErrorByName(
        'work_dir',
        $this->t('Work directory path must start with a leading slash.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('static_export.settings');
    $config
      ->set('uri.scheme', rtrim($form_state->getValue('stream_wrapper'), '/'))
      ->set('work_dir', rtrim($form_state->getValue('work_dir'), '/'))
      ->set('max_days_to_keep_logs', (int) $form_state->getValue('max_days_to_keep_logs'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
