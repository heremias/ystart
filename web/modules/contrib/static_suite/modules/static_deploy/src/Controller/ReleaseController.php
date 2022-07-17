<?php

namespace Drupal\static_deploy\Controller;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Routing\LocalRedirectResponse;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\static_build\Plugin\StaticBuilderPluginInterface;
use Drupal\static_build\Plugin\StaticBuilderPluginManagerInterface;
use Drupal\static_deploy\Plugin\StaticDeployerHelperInterface;
use Drupal\static_deploy\Plugin\StaticDeployerPluginManagerInterface;
use Drupal\static_suite\Release\Task\Batch\TaskBatchHandlerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Throwable;

/**
 * Provides various controllers to interact with releases on deploy context.
 */
class ReleaseController extends ControllerBase {

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\Request|null
   */
  protected $request;

  /**
   * Drupal Language Manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The static builder plugin manager.
   *
   * @var \Drupal\static_build\Plugin\StaticBuilderPluginManagerInterface
   */
  protected $staticBuilderPluginManager;

  /**
   * The static deployer plugin manager.
   *
   * @var \Drupal\static_deploy\Plugin\StaticDeployerPluginManagerInterface
   */
  protected $staticDeployerPluginManager;

  /**
   * The static deployer helper.
   *
   * @var \Drupal\static_deploy\Plugin\StaticDeployerHelperInterface
   */
  protected $staticDeployerHelper;

  /**
   * The task batch handler.
   *
   * @var \Drupal\static_suite\Release\Task\Batch\TaskBatchHandlerInterface
   */
  protected TaskBatchHandlerInterface $taskBatchHandler;

  /**
   * StaticBuilderReleasesController constructor.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Config factory.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   Drupal language manager.
   * @param \Drupal\static_build\Plugin\StaticBuilderPluginManagerInterface $staticBuilderPluginManager
   *   The static builder plugin manager.
   * @param \Drupal\static_deploy\Plugin\StaticDeployerPluginManagerInterface $staticDeployerPluginManager
   *   The static deployer plugin manager.
   * @param \Drupal\static_deploy\Plugin\StaticDeployerHelperInterface $staticDeployerHelper
   *   The static deployer helper.
   * @param \Drupal\static_suite\Release\Task\Batch\TaskBatchHandlerInterface $taskBatchHandler
   *   The task batch handler.
   */
  public function __construct(
    RequestStack $requestStack,
    AccountProxyInterface $currentUser,
    ConfigFactoryInterface $config_factory,
    LanguageManagerInterface $languageManager,
    StaticBuilderPluginManagerInterface $staticBuilderPluginManager,
    StaticDeployerPluginManagerInterface $staticDeployerPluginManager,
    StaticDeployerHelperInterface $staticDeployerHelper,
    TaskBatchHandlerInterface $taskBatchHandler
  ) {
    $this->request = $requestStack->getCurrentRequest();
    $this->currentUser = $currentUser;
    $this->configFactory = $config_factory;
    $this->languageManager = $languageManager;
    $this->staticBuilderPluginManager = $staticBuilderPluginManager;
    $this->staticDeployerPluginManager = $staticDeployerPluginManager;
    $this->staticDeployerHelper = $staticDeployerHelper;
    $this->taskBatchHandler = $taskBatchHandler;

  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('request_stack'),
      $container->get('current_user'),
      $container->get('config.factory'),
      $container->get('language_manager'),
      $container->get('plugin.manager.static_builder'),
      $container->get('plugin.manager.static_deployer'),
      $container->get('static_deploy.static_deployer_helper'),
      $container->get('static_suite.task_batch_handler'),
    );
  }

  /**
   * Shows a list of LIVE releases with deploy info.
   *
   * @param string $deployerId
   *   Static Deployer Id.
   * @param string $builderId
   *   Static Builder Id.
   *
   * @return array
   *   A render array as expected by the renderer.
   */
  public function listReleases(string $deployerId, string $builderId): array {
    try {
      $builder = $this->staticBuilderPluginManager->getInstance([
        'plugin_id' => $builderId,
        'configuration' => ['run-mode' => StaticBuilderPluginInterface::RUN_MODE_LIVE],
      ]);
      $deployer = $this->staticDeployerPluginManager->getInstance([
        'plugin_id' => $deployerId,
        'configuration' => ['builder-id' => $builderId],
      ]);
      $releaseManager = $deployer->getReleaseManager();
    }
    catch (Throwable $e) {
      $list['static_deploy_release_list'] = [
        '#markup' => '<p>' . $this->t('Unable to list releases:') . ' ' . $e->getMessage() . '</p>',
      ];
      return $list;
    }

    $allReleases = $releaseManager->getAllReleases();

    $rows = [];
    $ok = json_decode('"\u2714\ufe0f"', FALSE);
    $ko = json_decode('"\u274c"', FALSE);
    foreach ($allReleases as $delta => $release) {
      $releaseTask = $release->task($deployer->getTaskId());
      $row = [];
      $row['id'] = $release->uniqueId();

      // The progress bar should only appear once, for the latest release. This
      // way, we avoid problems with stale releases, that make this page reload
      // again and again in an infinite loop until the release is detected as
      // failed.
      if ($delta === 0 && $releaseTask->isRunning()) {
        $row['speed'] = '--';
        $row['percentage']['data'] = [
          '#theme' => 'progress_bar',
          '#attached' => [
            'drupalSettings' => [
              'batch' => [
                'delay' => 3000,
                'errorMessage' => $this->t('An error has occurred.'),
                'initMessage' => $this->t('Gathering deployment data...'),
                'uri' => Url::fromRoute('static_deploy.release_list.running_data.batch', [
                  'deployerId' => $deployerId,
                  'builderId' => $builderId,
                  'destination' => $this->request->getRequestUri(),
                ])->toString(),
              ],
            ],
            'library' => [
              'static_suite/batch',
            ],
          ],
        ];
      }
      else {
        $row['speed'] = $releaseTask->isStarted() ? $this->t('@secs seconds', ['@secs' => $releaseTask->getProcessBenchmark()]) : '--';
        $percentage = $releaseManager->getTaskSupervisor()
          ->getTaskPercentage($release->uniqueId(), $deployer->getTaskId());
        $row['percentage'] = $releaseTask->isFailed() ? '--' : $this->t('@percentage%', ['@percentage' => $percentage]);
      }

      $row['deploy-started'] = $releaseTask->isStarted() ? $ok : $ko;
      $row['deploy-done'] = $releaseTask->isDone() ? $ok : $ko;
      $row['deploy-log'] = $releaseTask->isStarted() ? [
        'data' => [
          '#type' => 'inline_template',
          '#template' => '<a href="' . Url::fromRoute(
              'static_deploy.log_viewer', [
                'builderId' => $builderId,
                'deployerId' => $deployerId,
                'uniqueId' => $release->uniqueId(),
              ]
          )->toString() . '" target="_blank">' . $this->t('View') . '</a>',
        ],
      ] : '--';
      $rows[] = $row;
    }

    $headers = [
      $this->t('Unique ID'),
      $this->t('Speed (~@averageSpeed secs)', [
        '@averageSpeed' => $releaseManager->getTaskSupervisor()
          ->getAverageTaskTime($deployer->getTaskId()),
      ]),
      $this->t('Deploy percentage'),
      $this->t('Started'),
      $this->t('Done'),
      $this->t('Log'),
    ];

    $list['release_list_header'] = [
      '#markup' => '<p>' .
      $this->t(
          'Releases deployed by <strong>"@deployer"</strong> (built by <strong>"@builder"</strong> in <strong>"@run-mode"</strong> mode). Keep in mind that releases created by "@cloud" builders are automatically deployed by the CI/CD service in charge of the build process.',
          [
            '@deployer' => $deployer->getPluginDefinition()['label'],
            '@builder' => $builder->getPluginDefinition()['label'],
            '@run-mode' => StaticBuilderPluginInterface::RUN_MODE_LIVE,
            '@cloud' => StaticBuilderPluginInterface::HOST_MODE_CLOUD,
          ])
      . '</p>',
    ];

    if ($this->currentUser->hasPermission('run deployments on demand')) {
      $list['run_new_build'] = [
        '#theme' => 'menu_local_action',
        '#link' => [
          'title' => $this->t('Run new deployment'),
          'url' => Url::fromRoute('static_deploy.run_deploy_on_demand', [
            'deployerId' => $deployerId,
            'builderId' => $builderId,
            'destination' => $this->request->getRequestUri(),
          ]),
        ],
        // Without this workaround, the action links will be rendered as <li>
        // with no wrapping <ul> element.
        // @todo Find a better approach to wrap local action links in a <ul>
        //   (https://www.drupal.org/node/3181052) or find a way for derivers to
        //   work with "appears_on" keys that have parameters on them.
        '#prefix' => '<ul class="action-links">',
        '#suffix' => '</ul>',
      ];
    }

    $list['release_list'] = [
      '#type' => 'table',
      '#header' => $headers,
      '#rows' => $rows,
      '#empty' => $this->t('No releases available. Please edit some content or run a build manually.'),
      '#attributes' => [
        'class' => ['block-add-table'],
      ],
    ];

    return $list;
  }

  /**
   * Get deploy data from a specific release.
   *
   * It provides build data about a specific release, no matter if its being
   * deployed at this moment or not.
   *
   * @param string $deployerId
   *   Static Deployer ID.
   * @param string $builderId
   *   Static Builder ID.
   * @param string $uniqueId
   *   Release's unique ID.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response.
   */
  public function getReleaseRunningData(string $deployerId, string $builderId, string $uniqueId): JsonResponse {
    return new JsonResponse(
      $this->staticDeployerHelper->getRunningDeployData($deployerId, $builderId, $uniqueId)
    );
  }

  /**
   * Get data from the release currently being deployed, among all releases.
   *
   * No need to specify a release, it will offer data from the one that is
   * running.
   *
   * @param string $deployerId
   *   Static Deployer ID.
   * @param string $builderId
   *   Static Builder ID.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response.
   */
  public function getAllReleasesRunningData(string $deployerId, string $builderId): JsonResponse {
    return new JsonResponse(
      $this->staticDeployerHelper->getRunningDeployData($deployerId, $builderId)
    );
  }

  /**
   * Get batch data from the release being deployed, among all releases.
   *
   * No need to specify a release, it will offer data from the one that is
   * running.
   *
   * @param string $deployerId
   *   Static Deployer ID.
   * @param string $builderId
   *   Static Builder ID.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse|\Drupal\Core\Routing\LocalRedirectResponse
   *   JSON response or a redirect.
   */
  public function getAllReleasesRunningDataBatch(string $deployerId, string $builderId): JsonResponse | LocalRedirectResponse {
    // Redirect when finished.
    if ($this->request->query->get('destination') && $this->request->query->get('op') === 'finished') {
      return new LocalRedirectResponse($this->request->query->get('destination'));
    }

    $taskData = $this->staticDeployerHelper->getRunningDeployData($deployerId, $builderId);

    // Use data from last deployment instead of the running one, if the running
    // one does not exist.
    if (empty($taskData['unique-id']) && !empty($taskData['last']['unique-id'])) {
      $taskData = $taskData['last'];
    }

    $logUrl = NULL;
    if ($taskData['unique-id'] && $this->currentUser->hasPermission('view static build files')) {
      $logUrl = Url::fromRoute('static_deploy.log_viewer', [
        'deployerId' => $deployerId,
        'builderId' => $builderId,
        'uniqueId' => $taskData['unique-id'],
      ])->toString();
    }

    $batchCallbackData = $this->taskBatchHandler->prepareBatchCallbackData($taskData, "Deploying", $logUrl);

    return new JsonResponse($batchCallbackData);
  }

}
