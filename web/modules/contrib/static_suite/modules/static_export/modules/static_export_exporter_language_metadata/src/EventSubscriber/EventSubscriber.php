<?php

namespace Drupal\static_export_exporter_language_metadata\EventSubscriber;

use Drupal\static_export\EventSubscriber\LanguageModifiedExporterEventSubscriberBase;

/**
 * Event subscriber for language metadata exporter.
 *
 * Exports language metadata when some configuration related to languages is
 * modified.
 */
class EventSubscriber extends LanguageModifiedExporterEventSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function getExporterId(): string {
    return 'language-metadata';
  }

}
