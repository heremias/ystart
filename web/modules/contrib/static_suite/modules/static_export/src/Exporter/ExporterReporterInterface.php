<?php

namespace Drupal\static_export\Exporter;

/**
 * An interface for export reporters.
 */
interface ExporterReporterInterface {

  /**
   * Get changed files after a export operation occurred.
   *
   * @param string $uniqueId
   *   A unique id.
   *
   * @return array|null
   *   An array of changed files or null if nothing found
   */
  public function getChangedFilesAfter(string $uniqueId): ?array;

}
