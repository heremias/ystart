<?php

namespace Drupal\static_export\Exporter\Output\Uri\Resolver;

use Exception;

/**
 * Base URI resolver for exported items.
 */
abstract class ExporterUriResolverBase implements ExporterUriResolverInterface {

  /**
   * {@inheritdoc}
   */
  public function getUris(): array {
    $uris = [];
    try {
      // Remove null values by using array_filter.
      $uris = array_filter(
        array_merge(
          [$this->getMainUri()],
          $this->getVariantUris(),
          $this->getTranslationUris()
        )
      );
    }
    catch (Exception $e) {
      trigger_error($e, E_USER_WARNING);
    }

    return $uris;
  }

}
