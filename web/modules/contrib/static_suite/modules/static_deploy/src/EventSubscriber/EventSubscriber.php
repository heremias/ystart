<?php

namespace Drupal\static_deploy\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\static_build\Event\StaticBuildEvent;
use Drupal\static_build\Event\StaticBuildEvents;
use Drupal\static_deploy\Plugin\StaticDeployerPluginManagerInterface;
use Drupal\static_suite\Entity\EntityUtils;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event subscriber for Static Deploy.
 */
class EventSubscriber implements EventSubscriberInterface {

  /**
   * Config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Static Deployer Manager.
   *
   * @var \Drupal\static_deploy\Plugin\StaticDeployerPluginManagerInterface
   */
  protected $staticDeployerPluginManager;


  /**
   * Entity Utils.
   *
   * @var \Drupal\static_suite\Entity\EntityUtils
   */
  protected $entityUtils;

  /**
   * Constructs the subscriber.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Config factory.
   * @param \Drupal\static_deploy\Plugin\StaticDeployerPluginManagerInterface $staticDeployerPluginManager
   *   Static Deployer Manager.
   * @param \Drupal\static_suite\Entity\EntityUtils $entityUtils
   *   Utils for working with entities.
   */
  public function __construct(ConfigFactoryInterface $config_factory, StaticDeployerPluginManagerInterface $staticDeployerPluginManager, EntityUtils $entityUtils) {
    $this->configFactory = $config_factory;
    $this->staticDeployerPluginManager = $staticDeployerPluginManager;
    $this->entityUtils = $entityUtils;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[StaticBuildEvents::CHAINED_STEP_END][] = ['requestDeploy'];
    return $events;
  }

  /**
   * Reacts to a StaticBuildEvents::ENDS event.
   *
   * @param \Drupal\static_build\Event\StaticBuildEvent $event
   *   The Static Build event.
   *
   * @return \Drupal\static_build\Event\StaticBuildEvent
   *   The processed event.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\static_suite\StaticSuiteException
   */
  public function requestDeploy(StaticBuildEvent $event): StaticBuildEvent {
    // Check if received event is requesting a deployment.
    $builder = $event->getBuilder();
    $configuration = $builder->getConfiguration();
    if (!$configuration['request-deploy']) {
      $builder->logMessage('[deploy] Builder is not allowed to trigger a deployment.');
      return $event;
    }

    $deployers = $this->configFactory->get('static_deploy.settings')
      ->get('deployers');
    if (!is_array($deployers) || count($deployers) === 0) {
      $builder->logMessage('[deploy] No deployers available.');
      return $event;
    }

    foreach ($deployers as $deployerId) {
      $staticDeployer = $this->staticDeployerPluginManager->getInstance([
        'plugin_id' => $deployerId,
        'configuration' => [
          'builder-id' => $builder->getPluginId(),
          'console-output' => $configuration['console-output'] ?? NULL,
        ],
      ]);

      // Trigger a deployment.
      $event->getBuilder()->logMessage('[deploy] Deployment triggered.');
      $staticDeployer->init();
    }

    return $event;
  }

}
