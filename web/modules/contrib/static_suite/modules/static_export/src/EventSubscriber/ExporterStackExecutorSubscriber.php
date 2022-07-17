<?php

namespace Drupal\static_export\EventSubscriber;

use Drupal\static_export\Exporter\Stack\ExporterStackExecutorInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\FinishRequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Provides a subscriber that executes the exporter stack on kernel termination.
 */
class ExporterStackExecutorSubscriber implements EventSubscriberInterface {

  /**
   * The exporter stack executor.
   *
   * @var \Drupal\static_export\Exporter\Stack\ExporterStackExecutorInterface
   */
  protected ExporterStackExecutorInterface $exporterStackExecutor;

  /**
   * Constructs a new ExporterStackExecutorSubscriber instance.
   *
   * @param \Drupal\static_export\Exporter\Stack\ExporterStackExecutorInterface $exporterStackExecutor
   *   The exporter stack executor.
   */
  public function __construct(ExporterStackExecutorInterface $exporterStackExecutor) {
    $this->exporterStackExecutor = $exporterStackExecutor;
  }

  /**
   * Registers the methods in this class that should be listeners.
   *
   * @return array
   *   An array of event listener definitions.
   */
  public static function getSubscribedEvents() {
    $events[KernelEvents::FINISH_REQUEST][] = ['onFinishRequest', 200];
    return $events;
  }

  /**
   * Execute exporter stack when request is finished.
   */
  public function onFinishRequest(FinishRequestEvent $event): void {
    $this->exporterStackExecutor->execute();
  }

}
