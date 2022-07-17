<?php

namespace Drupal\static_export\Exporter;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\static_export\File\FileCollectionWriter;

/**
 * Exporter reporter service.
 */
class ExporterReporter implements ExporterReporterInterface {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Resolver service constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    $this->configFactory = $config_factory;
  }

  /**
   * Get changed files after a export operation occurred.
   *
   * @param string $uniqueId
   *   A unique id.
   *
   * @return array|null
   *   An array of changed files or null if nothing found
   */
  public function getChangedFilesAfter(string $uniqueId): ?array {
    $changedFiles = [];
    $changedFilesLogPath = $this->configFactory->get("static_export.settings")
      ->get("work_dir") . "/" . FileCollectionWriter::LOCK_EXECUTED_LOG_FILE;
    $lines = file($changedFilesLogPath);
    if (is_array($lines)) {
      foreach ($lines as $line) {
        if (substr($line, 0, 32) > $uniqueId) {
          $data = $this->getDataFromLogLine($line);
          if (isset($data["file"]["absolute-path"])) {
            // Use absolute path as key to avoid repeated entries.
            $changedFiles[$data["file"]["absolute-path"]] = $data["file"];
          }
        }
      }
    }

    if (count($changedFiles) > 0) {
      // Remove absolute path from key.
      return array_values($changedFiles);
    }
    return NULL;
  }

  /**
   * Parse changed files log line.
   *
   * @param string $line
   *   Log line.
   *
   * @return array|null
   *   An array with parsed data or null if nothing found.
   */
  protected function getDataFromLogLine(string $line): ?array {
    if (preg_match("/^(\S+) (\S+) \[ID: ([^]]+)] (.+)/", $line, $matches)) {
      [$uniqueId, $operation, $fileId] = $matches;
      $separatorPosition = strrpos($matches[4], " | ");
      $fileLabel = substr($matches[4], 0, $separatorPosition);
      $fileAbsolutePath = substr($matches[4], $separatorPosition + 3);
      return [
        "unique-id" => $uniqueId,
        "operation" => $operation,
        "file" => [
          "id" => $fileId,
          "label" => $fileLabel,
          "absolute-path" => $fileAbsolutePath,
        ],
      ];
    }
    return NULL;
  }

}
