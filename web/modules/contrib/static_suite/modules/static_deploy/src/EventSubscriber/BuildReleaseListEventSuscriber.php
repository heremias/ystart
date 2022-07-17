<?php

namespace Drupal\static_deploy\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\static_build\Event\BuildReleaseListEvents;
use Drupal\static_build\Plugin\StaticBuilderPluginManagerInterface;
use Drupal\static_deploy\Plugin\StaticDeployerPluginManagerInterface;
use Drupal\static_suite\Event\DataEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event subscriber for Modify Build release list.
 */
class BuildReleaseListEventSuscriber implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * Config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The static builder manager.
   *
   * @var \Drupal\static_build\Plugin\StaticBuilderPluginManagerInterface
   */
  protected $staticBuilderPluginManager;

  /**
   * Static Deployer Manager.
   *
   * @var \Drupal\static_deploy\Plugin\StaticDeployerPluginManagerInterface
   */
  protected $staticDeployerPluginManager;

  /**
   * Constructs the subscriber.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Config factory.
   * @param \Drupal\static_build\Plugin\StaticBuilderPluginManagerInterface $static_builder_manager
   *   The static builder plugin manager.
   * @param \Drupal\static_deploy\Plugin\StaticDeployerPluginManagerInterface $staticDeployerPluginManager
   *   Static Deployer Manager.
   */
  public function __construct(ConfigFactoryInterface $config_factory, StaticBuilderPluginManagerInterface $staticBuilderPluginManager, StaticDeployerPluginManagerInterface $staticDeployerPluginManager) {
    $this->configFactory = $config_factory;
    $this->staticBuilderPluginManager = $staticBuilderPluginManager;
    $this->staticDeployerPluginManager = $staticDeployerPluginManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[BuildReleaseListEvents::TABLE_BUILT][] = ['alterBuildReleaseListTable'];
    return $events;
  }

  /**
   * Reacts to a ReleaseListEvents::TABLE_BUILT event.
   *
   * @param \Drupal\static_suite\Event\DataEvent $event
   *   The ReleaseListEvents::ROW_BUILT data event.
   *
   * @return \Drupal\static_suite\Event\DataEvent
   *   The processed event.
   */
  public function alterBuildReleaseListTable(DataEvent $event): DataEvent {
    $data = $event->getData();
    $deployers = $this->configFactory->get('static_deploy.settings')
      ->get('deployers');
    if (!is_array($deployers) || count($deployers) === 0) {
      return $event;
    }

    // Early opt-out if build is not local.
    $localBuilders = $this->staticBuilderPluginManager->getLocalDefinitions();
    if (isset($data['builderId']) && !array_key_exists($data['builderId'], $localBuilders)) {
      return $event;
    }

    $ok = json_decode('"\u2714\ufe0f"', FALSE);
    $ko = json_decode('"\u274c"', FALSE);
    foreach ($deployers as $deployerId) {
      $mustShow = FALSE;
      $deployer = $this->staticDeployerPluginManager->getInstance([
        'plugin_id' => $deployerId,
        'configuration' => ['builder-id' => $data['builderId']],
      ]);
      $releaseManager = $deployer->getReleaseManager();
      $releases = $releaseManager->getAllReleases();
      foreach ($releases as $release) {
        foreach ($data['rows'] as $key => $buildReleases) {
          if ($release->uniqueId() === $buildReleases['id']) {
            $releaseTask = $release->task($deployer->getTaskId());

            if ($releaseTask->isStarted()) {
              $mustShow = TRUE;
              $result = $releaseTask->isDone() ? $ok : $ko;
              $data['rows'][$key]['deploy_' . $deployerId] = [
                'data' => [
                  '#type' => 'inline_template',
                  '#template' => $result . ' (<a href="' . Url::fromRoute(
                      'static_deploy.log_viewer', [
                        'builderId' => $data['builderId'],
                        'deployerId' => $deployerId,
                        'uniqueId' => $release->uniqueId(),
                      ]
                  )
                    ->toString() . '" target="_blank">' . $this->t('Log') . '</a>)',
                ],
              ];
            }
            else {
              $data['rows'][$key]['deploy_' . $deployerId] = $ko;
            }
          }
        }

      }
      if ($mustShow) {
        $data['header'][] = $this->t('Deployed to @deployerId', ['@deployerId' => $deployerId]);
      }
      else {
        foreach ($data['rows'] as $key => $buildReleases) {
          if (isset($data['rows'][$key]['deploy_' . $deployerId])) {
            unset($data['rows'][$key]['deploy_' . $deployerId]);
          }
        }
      }
    }
    $event->setData($data);
    return $event;
  }

}
