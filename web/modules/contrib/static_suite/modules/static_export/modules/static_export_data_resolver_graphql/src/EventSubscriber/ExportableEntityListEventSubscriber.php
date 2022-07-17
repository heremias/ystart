<?php

namespace Drupal\static_export_data_resolver_graphql\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\static_export\Entity\ExportableEntityInterface;
use Drupal\static_export\Event\ExportableEntityListEvents;
use Drupal\static_suite\Event\DataEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event subscriber for Exportable Entity List.
 */
class ExportableEntityListEventSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * Config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Config factory.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[ExportableEntityListEvents::ROW_BUILT][] = ['onRowBuilt'];
    return $events;
  }

  /**
   * Reacts to a ExportableEntityListEvents::ROW_BUILT event.
   *
   * @param \Drupal\static_suite\Event\DataEvent $event
   *   The Exportable Entity List event.
   *
   * @return \Drupal\static_suite\Event\DataEvent
   *   The processed event.
   */
  public function onRowBuilt(DataEvent $event): DataEvent {
    $eventData = $event->getData();
    if (!empty($eventData['entity']) && $eventData['entity'] instanceof ExportableEntityInterface && $eventData['entity']->getDataResolver() === 'graphql' && !empty($eventData['row'])) {
      $graphqlFile = $eventData['entity']->id() . '.gql';
      $graphqlDir = $eventData['entity']->getEntityTypeIdString();
      $gqlFileRelative = $this->configFactory->get('static_export_data_resolver_graphql.settings')
        ->getOriginal('dir', FALSE) . '/' . $graphqlDir . '/' . $graphqlFile;
      $eventData['row']['resolver'] = [
        'data' =>
          [
            '#markup' => $eventData['row']['resolver'] . '<br/>(' .
            $this->t(
                'query defined in <abbr title="relative path: @graphql_file_relative">%graphql_file</abbr>',
                [
                  '@graphql_file_relative' => $gqlFileRelative,
                  '%graphql_file' => $graphqlFile,
                ]
            )
            . ')',
          ],
      ];
      $event->setData($eventData);
    }
    return $event;
  }

}
