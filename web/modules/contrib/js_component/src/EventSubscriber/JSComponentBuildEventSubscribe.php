<?php

namespace Drupal\js_component\EventSubscriber;

use Drupal\js_component\Event\Events;
use Drupal\js_component\Event\BuildComponentDataEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Define the JS component build event.
 */
class JSComponentBuildEventSubscribe implements EventSubscriberInterface {

  /**
   * {@inheritDoc}
   */
  public static function getSubscribedEvents() {
    return [
      Events::BUILD_COMPONENT_DATA => [
        ['buildJSComponentData'],
        ['buildJSProviderData']
      ]
    ];
  }

  /**
   * Build the JS component data.
   *
   * @param \Drupal\js_component\Event\BuildComponentDataEvent $event
   *   The build component event instance.
   */
  public function buildJSComponentData(
    BuildComponentDataEvent $event
  ) {
    if ($data = $event->getConfiguration()['component_data']) {
      $event->addComponentData($data);
    }
  }

  /**
   * Build the JS provider data.
   *
   * @param \Drupal\js_component\Event\BuildComponentDataEvent $event
   *   The build component event instance.
   */
  public function buildJSProviderData(
    BuildComponentDataEvent $event
  ) {
    $component = $event->getComponent();
    $settings = $event->getConfiguration()['js_component'];

    if ($provider = $component->dataProviderClassHandler($settings)) {
      $event->addComponentData($provider->fetch());
    }
  }
}
