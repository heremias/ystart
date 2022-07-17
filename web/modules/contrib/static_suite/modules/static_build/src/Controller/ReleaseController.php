<?php

namespace Drupal\static_build\Controller;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Routing\LocalRedirectResponse;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\static_build\Event\BuildReleaseListEvents;
use Drupal\static_build\Plugin\StaticBuilderHelperInterface;
use Drupal\static_build\Plugin\StaticBuilderPluginInterface;
use Drupal\static_build\Plugin\StaticBuilderPluginManagerInterface;
use Drupal\static_suite\Event\DataEvent;
use Drupal\static_suite\Release\Task\Batch\TaskBatchHandlerInterface;
use Drupal\static_suite\Utility\DirectoryDownloadHelperInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Provides various controllers to interact with releases on build context.
 */
class ReleaseController extends ControllerBase {

  public const DOWNLOAD_SEPARATOR = '--';

  /**
   * Request stack.
   *
   * @var \Symfony\Component\HttpFoundation\Request|null
   */
  protected $request;

  /**
   * Event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcher
   */
  protected $eventDispatcher;

  /**
   * The static builder plugin manager.
   *
   * @var \Drupal\static_build\Plugin\StaticBuilderPluginManagerInterface
   */
  protected $staticBuilderPluginManager;

  /**
   * The static builder helper.
   *
   * @var \Drupal\static_build\Plugin\StaticBuilderHelperInterface
   */
  protected $staticBuilderHelper;

  /**
   * The static builder plugin.
   *
   * @var \Drupal\static_build\Plugin\StaticBuilderPluginInterface
   */
  protected $builder;

  /**
   * The task batch handler.
   *
   * @var \Drupal\static_suite\Release\Task\Batch\TaskBatchHandlerInterface
   */
  protected TaskBatchHandlerInterface $taskBatchHandler;

  /**
   * The directory download helper.
   *
   * @var \Drupal\static_suite\Utility\DirectoryDownloadHelperInterface
   */
  protected DirectoryDownloadHelperInterface $directoryDownloadHelper;

  /**
   * Static Builder Releases Controller constructor.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   Request stack.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher service.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Config factory.
   * @param \Drupal\static_build\Plugin\StaticBuilderPluginManagerInterface $staticBuilderPluginManager
   *   The static builder plugin manager.
   * @param \Drupal\static_build\Plugin\StaticBuilderHelperInterface $staticBuilderHelper
   *   The static builder helper.
   * @param \Drupal\static_suite\Release\Task\Batch\TaskBatchHandlerInterface $taskBatchHandler
   *   The task batch handler.
   * @param \Drupal\static_suite\Utility\DirectoryDownloadHelperInterface $directoryDownloadHelper
   *   The directory download helper.
   */
  public function __construct(
    RequestStack $requestStack,
    EventDispatcherInterface $event_dispatcher,
    AccountProxyInterface $currentUser,
    ConfigFactoryInterface $config_factory,
    StaticBuilderPluginManagerInterface $staticBuilderPluginManager,
    StaticBuilderHelperInterface $staticBuilderHelper,
    TaskBatchHandlerInterface $taskBatchHandler,
    DirectoryDownloadHelperInterface $directoryDownloadHelper
  ) {
    $this->request = $requestStack->getCurrentRequest();
    $this->eventDispatcher = $event_dispatcher;
    $this->currentUser = $currentUser;
    $this->configFactory = $config_factory;
    $this->staticBuilderPluginManager = $staticBuilderPluginManager;
    $this->staticBuilderHelper = $staticBuilderHelper;
    $this->taskBatchHandler = $taskBatchHandler;
    $this->directoryDownloadHelper = $directoryDownloadHelper;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('request_stack'),
      $container->get('event_dispatcher'),
      $container->get('current_user'),
      $container->get('config.factory'),
      $container->get('plugin.manager.static_builder'),
      $container->get('static_build.static_builder_helper'),
      $container->get('static_suite.task_batch_handler'),
      $container->get('static_suite.directory_download_helper')
    );
  }

  /**
   * Trigger an event with the ability to modify table headers and rows.
   *
   * @param array $header
   *   Header info.
   * @param array $rows
   *   Rows data.
   * @param string $builderId
   *   BuilderID.
   * @param string $runMode
   *   Run mode.
   *
   * @return array
   *   Header and rows data from BuildReleaseListEvents::TABLE_BUILT event.
   */
  protected function modifyTable(array $header, array $rows, string $builderId, string $runMode): array {
    $event = new DataEvent([
      'header' => $header,
      'rows' => $rows,
      'builderId' => $builderId,
      'runMode' => $runMode,
    ]);
    $processedEvent = $this->eventDispatcher->dispatch($event, BuildReleaseListEvents::TABLE_BUILT);
    return $processedEvent->getData();
  }

  /**
   * Shows a list of releases.
   *
   * @param string $builderId
   *   Builder id.
   * @param string $runMode
   *   A run mode to get releases for. Usually live or preview.
   *
   * @return array
   *   A render array as expected by the renderer.
   */
  public function listReleases(string $builderId, string $runMode): array {
    try {
      $builder = $this->staticBuilderPluginManager->getInstance([
        'plugin_id' => $builderId,
        'configuration' => ['run-mode' => $runMode],
      ]);
      $releaseManager = $builder->getReleaseManager();
    }
    catch (Throwable $e) {
      $list['static_build_release_list'] = [
        '#markup' => '<p>' . $this->t('Unable to list releases:') . ' ' . $e->getMessage() . '</p>',
      ];
      return $list;
    }

    $allReleases = $releaseManager->getAllReleases();

    $showDownload = $builder->isLocal() && $this->currentUser->hasPermission('download release');

    $header = [
      $this->t('Unique ID'),
      $this->t('Speed (~@averageSpeed secs)', [
        '@averageSpeed' => $releaseManager->getTaskSupervisor()
          ->getAverageTaskTime($builder->getTaskId()),
      ]),
      $this->t('Build percentage'),
      $this->t('Is current'),
      $this->t('Successfully built'),
      $this->t('Build log'),
    ];

    if ($showDownload) {
      $header[] = $this->t('Download');
    }

    $rows = [];
    $ok = json_decode('"\u2714\ufe0f"', FALSE);
    $ko = json_decode('"\u274c"', FALSE);
    foreach ($allReleases as $delta => $release) {
      $releaseTask = $release->task($builder->getTaskId());
      $row = [];

      $uniqueId = $release->uniqueId();

      // Keep this "id" as-is, and do not turn it into a link or similar,
      // because it's used by listeners of BuildReleaseListEvents::TABLE_BUILT.
      $row['id'] = $uniqueId;

      // The progress bar should only appear once, for the latest release. This
      // way, we avoid problems with stale releases, that make this page reload
      // again and again in an infinite loop until the release is marked as
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
                'initMessage' => $this->t('Gathering build data...'),
                'nextStepMessage' => $this->t('New build process found, reloading...'),
                'uri' => Url::fromRoute('static_build.release_list.running_data.batch', [
                  'builderId' => $builderId,
                  'runMode' => $runMode,
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
        $row['speed'] = $releaseTask->isFailed() ? '--' : $this->t('@secs seconds', ['@secs' => $releaseTask->getProcessBenchmark()]);
        $percentage = $releaseManager->getTaskSupervisor()
          ->getTaskPercentage($uniqueId, $builder->getTaskId());
        $row['percentage'] = $releaseTask->isFailed() ? '--' : $this->t('@percentage%', ['@percentage' => $percentage]);
      }

      $row['current'] = $releaseManager->isCurrent($uniqueId) ? $ok : $ko;
      $row['build-done'] = $releaseTask->isDone() ? $ok : $ko;
      $row['build-log']['data'] = [
        '#type' => 'link',
        '#url' => Url::fromRoute('static_build.log_viewer', [
          'builderId' => $builderId,
          'runMode' => $runMode,
          'uniqueId' => $uniqueId,
        ]),
        '#title' => $this->t('View'),
        '#attributes' => [
          'target' => '_blank',
        ],
      ];

      if ($showDownload) {
        $row['download']['data'] = [
          '#type' => 'link',
          '#url' => Url::fromRoute('static_build.release.download', [
            'builderId' => $builderId,
            'runMode' => $runMode,
            'uniqueId' => $uniqueId,
          ]),
          '#title' => $this->t('Download'),
          '#attributes' => [
            'title' => $this->t('Download'),
          ],
        ];
      }

      $rows[] = $row;
    }

    $table = $this->modifyTable(
      $header,
      $rows,
      $builderId,
      $runMode
    );

    $headerMessage = $this->t(
      'Releases built by <strong>"@builder"</strong> in <strong>"@run-mode"</strong> mode.',
      [
        '@builder' => $builder->getPluginDefinition()['label'],
        '@run-mode' => $runMode,
        '@cloud' => StaticBuilderPluginInterface::HOST_MODE_CLOUD,
      ]
    );

    $subHeaderMessage = NULL;
    if ($runMode === StaticBuilderPluginInterface::RUN_MODE_PREVIEW) {
      $subHeaderMessage = $this->t(
        'Keep in mind that "@cloud" builders do not support "@run-mode" mode.',
        [
          '@cloud' => StaticBuilderPluginInterface::HOST_MODE_CLOUD,
          '@run-mode' => $runMode,
        ]
      );
    }

    $list['release_list_header'] = [
      '#markup' => '<p>' . $headerMessage . ' ' . $subHeaderMessage . '</p>',
    ];

    if ($this->currentUser->hasPermission('run builds on demand')) {
      $list['run_new_build'] = [
        '#theme' => 'menu_local_action',
        '#link' => [
          'title' => $this->t('Run new build'),
          'url' => Url::fromRoute('static_build.run_build_on_demand', [
            'builderId' => $builder->getPluginId(),
            'runMode' => $runMode,
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
      '#header' => $table['header'],
      '#rows' => $table['rows'],
      '#empty' => $this->t('No releases available. Please edit some content or run a build manually.'),
      '#attributes' => [
        'class' => ['block-add-table'],
      ],
    ];

    return $list;
  }

  /**
   * Get build data from a specific release, regardless of being built or not.
   *
   * It provides build data about a specific release, no matter if its being
   * built at this moment or not.
   *
   * @param string $builderId
   *   Builder id.
   * @param string $runMode
   *   A run mode. Usually live or preview.
   * @param string $uniqueId
   *   Release's unique id.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response.
   */
  public function getReleaseRunningData(string $builderId, string $runMode, string $uniqueId): JsonResponse {
    return new JsonResponse(
      $this->staticBuilderHelper->getRunningBuildData($builderId, $runMode, $uniqueId)
    );
  }

  /**
   * Get data from the release currently being built, among all releases.
   *
   * No need to specify a release, it will offer data from the one that is
   * running.
   *
   * @param string $builderId
   *   Builder id.
   * @param string $runMode
   *   A run mode. Usually live or preview.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response.
   */
  public function getAllReleasesRunningData(string $builderId, string $runMode): JsonResponse {
    return new JsonResponse(
      $this->staticBuilderHelper->getRunningBuildData($builderId, $runMode)
    );
  }

  /**
   * Get batch data from the release currently being built, among all releases.
   *
   * No need to specify a release, it will offer data from the one that is
   * running.
   *
   * @param string $builderId
   *   Builder id.
   * @param string $runMode
   *   A run mode. Usually live or preview.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse|\Drupal\Core\Routing\LocalRedirectResponse
   *   JSON response or a redirect.
   */
  public function getAllReleasesRunningDataBatch(string $builderId, string $runMode): JsonResponse | LocalRedirectResponse {
    // Redirect when finished.
    if ($this->request->query->get('destination') && $this->request->query->get('op') === 'finished') {
      return new LocalRedirectResponse($this->request->query->get('destination'));
    }

    $taskData = $this->staticBuilderHelper->getRunningBuildData($builderId, $runMode);

    // Use data from last build instead of the running one, if the running one
    // does not exist.
    if (empty($taskData['unique-id']) && !empty($taskData['last']['unique-id'])) {
      $taskData = $taskData['last'];
    }

    $logUrl = NULL;
    if ($taskData['unique-id'] && $this->currentUser->hasPermission('view static build files')) {
      $logUrl = Url::fromRoute('static_build.log_viewer', [
        'builderId' => $builderId,
        'runMode' => $runMode,
        'uniqueId' => $taskData['unique-id'],
      ])->toString();
    }

    $batchCallbackData = $this->taskBatchHandler->prepareBatchCallbackData($taskData, "Building", $logUrl);

    return new JsonResponse($batchCallbackData);
  }

  /**
   * Controller to download all files in a release, compressed as tar.gz.
   *
   * @param string $builderId
   *   Builder id.
   * @param string $runMode
   *   A run mode. Usually live or preview.
   * @param string|null $uniqueId
   *   Release's unique id. Optional. If none provided, it will use the one
   *   from current release.
   *
   * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
   *   The transferred file as response.
   */
  public function download(string $builderId, string $runMode, string $uniqueId = NULL): BinaryFileResponse {
    $localBuilderDefinitions = $this->staticBuilderPluginManager->getLocalDefinitions();
    if (!isset($localBuilderDefinitions[$builderId])) {
      throw new NotFoundHttpException('Downloading a release is only available for local builders.');
    }

    try {
      $builder = $this->staticBuilderPluginManager->getInstance([
        'plugin_id' => $builderId,
        'configuration' => ['run-mode' => $runMode],
      ]);
      $releaseManager = $builder->getReleaseManager();
    }
    catch (Throwable $e) {
      throw new NotFoundHttpException($e->getMessage());
    }

    if ($uniqueId) {
      $release = $releaseManager->getRelease($uniqueId);
    }
    else {
      $release = $releaseManager->getCurrentRelease();
    }
    if (!$release) {
      throw new NotFoundHttpException();
    }

    return $this->directoryDownloadHelper->download(
      $release->getDir(),
      implode(self::DOWNLOAD_SEPARATOR, [$builderId, $runMode, $uniqueId])
    );
  }

}
