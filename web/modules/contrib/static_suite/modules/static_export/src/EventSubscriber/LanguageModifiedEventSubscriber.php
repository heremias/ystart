<?php

namespace Drupal\static_export\EventSubscriber;

use Drupal\Core\Config\ConfigCrudEvent;
use Drupal\Core\Config\ConfigEvents;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\language\Config\LanguageConfigOverrideCrudEvent;
use Drupal\language\Config\LanguageConfigOverrideEvents;
use Drupal\static_export\Event\LanguageModifiedEvents;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Event subscriber to centralize all events that affect a language.
 *
 * It triggers another event data when system site, a language entity, language
 * negotiation or language type is modified in any way.
 *
 * Its called "modified" and not "updated" because "update" makes reference to
 * the U in CRUD, but this Event Subscriber listens to more actions.
 */
class LanguageModifiedEventSubscriber implements EventSubscriberInterface {

  /**
   * Drupal config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * Event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected EventDispatcherInterface $eventDispatcher;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   Drupal config factory.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   Event dispatcher.
   */
  public function __construct(ConfigFactoryInterface $configFactory, EventDispatcherInterface $eventDispatcher) {
    $this->configFactory = $configFactory;
    $this->eventDispatcher = $eventDispatcher;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[ConfigEvents::SAVE][] = ['configCrudHandler'];
    $events[ConfigEvents::RENAME][] = ['configCrudHandler'];
    $events[ConfigEvents::DELETE][] = ['configCrudHandler'];
    $events[LanguageConfigOverrideEvents::SAVE_OVERRIDE][] = ['configSaveOverrideHandler'];
    $events[LanguageConfigOverrideEvents::DELETE_OVERRIDE][] = ['configSaveOverrideHandler'];
    return $events;
  }

  /**
   * Reacts to a CRUD event on language.negotiation.
   *
   * @param \Drupal\Core\Config\ConfigCrudEvent $event
   *   The configuration event.
   */
  public function configCrudHandler(ConfigCrudEvent $event): void {
    $configName = $event->getConfig()->getName();
    if (self::affectsLanguages($configName)) {
      $this->dispatchEvent();
    }
  }

  /**
   * Reacts to a CRUD override event on language.negotiation.
   *
   * @param \Drupal\language\Config\LanguageConfigOverrideCrudEvent $event
   *   The configuration event.
   */
  public function configSaveOverrideHandler(LanguageConfigOverrideCrudEvent $event): void {
    $configName = $event->getLanguageConfigOverride()->getName();
    if (self::affectsLanguages($configName)) {
      $this->dispatchEvent();
    }
  }

  /**
   * Dispatch.
   */
  protected function dispatchEvent(): void {
    $this->eventDispatcher->dispatch(new Event(), LanguageModifiedEvents::LANGUAGE_MODIFIED);
  }

  /**
   * Tells whether a configuration name affects language data.
   *
   * @param string $configName
   *   Configuration name.
   *
   * @return bool
   *   True if affects language data, false otherwise.
   */
  protected static function affectsLanguages(string $configName): bool {
    // system.site is where the default language is stored.
    return (
      $configName === 'language.negotiation' ||
      $configName === 'system.site' ||
      $configName === 'language.types' ||
      str_starts_with($configName, 'language.entity.')
    );
  }

}
