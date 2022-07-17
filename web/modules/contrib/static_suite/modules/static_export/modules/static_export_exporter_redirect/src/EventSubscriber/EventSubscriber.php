<?php

namespace Drupal\static_export_exporter_redirect\EventSubscriber;

use Drupal\static_export\EventSubscriber\LanguageModifiedExporterEventSubscriberBase;

/**
 * Event subscriber for redirect exporter.
 *
 * Exports redirect data when some configuration related to languages is
 * modified.
 */
class EventSubscriber extends LanguageModifiedExporterEventSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function getExporterId(): string {
    return 'redirect';
  }

}
