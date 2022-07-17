<?php

namespace Drupal\static_export\File;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManager;
use Drupal\Core\Url;
use Drupal\static_suite\Utility\StaticSuiteUtilsInterface;

/**
 * A class for formatting a FileCollection.
 */
class FileCollectionFormatter {

  /**
   * A FileCollection to format.
   *
   * @var \Drupal\static_export\File\FileCollection
   */
  protected $fileCollection;

  /**
   * Config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Static Suite utils.
   *
   * @var \Drupal\static_suite\Utility\StaticSuiteUtilsInterface
   */
  protected $staticSuiteUtils;

  /**
   * FileCollectionFormatter constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The base config provider.
   * @param \Drupal\static_suite\Utility\StaticSuiteUtilsInterface $staticSuiteUtils
   *   Static Suite utils.
   */
  public function __construct(ConfigFactoryInterface $config_factory, StaticSuiteUtilsInterface $staticSuiteUtils) {
    $this->configFactory = $config_factory;
    $this->staticSuiteUtils = $staticSuiteUtils;
  }

  /**
   * Set a FileCollection.
   *
   * @param \Drupal\static_export\File\FileCollection $fileCollection
   *   A FileCollection to format.
   */
  public function setFileCollection(FileCollection $fileCollection) {
    $this->fileCollection = $fileCollection;
  }

  /**
   * Returns a default string representation (in text format)
   *
   * @return string
   *   A textual representation.
   */
  public function __toString() {
    return implode("\n", $this->getTextLines());
  }

  /**
   * Formats a FileCollection using plain text format and returns an array.
   *
   * @param int $delta
   *   Batch delta, useful only in a batch operation.
   * @param int $total
   *   Total size of batch, useful only in a batch operation.
   * @param bool $addHeader
   *   A flag to unconditionally add the header.
   *
   * @return array
   *   An array of formatted text lines.
   */
  public function getTextLines(int $delta = 0, int $total = 0, bool $addHeader = FALSE) {
    $lines = [];
    foreach ($this->fileCollection->getFileItems() as $index => $fileItem) {
      if ($total === 0) {
        if ($index === 0) {
          if ($addHeader || !$this->staticSuiteUtils->isRunningOnCli()) {
            $line = "File Collection " . $this->fileCollection->uniqueId();
            $line .= sprintf(" [%6s] ", $this->fileCollection->getBenchmark() . "s");
            $runMode = $this->fileCollection->isPreview() ? 'preview' : 'live';
            $line .= "[" . $runMode . "]";
            $lines[] = $line;
            $lines[] = str_repeat('=', 80);
          }
          $line = sprintf("%2s ", json_decode('"\u2002"', TRUE));
        }
        else {
          $line = sprintf("%2s ", json_decode('"\u21B3"', TRUE));
        }
      }
      else {
        $fieldLength = (strlen($total) * 2) + 6;
        $deltaLength = strlen($total);
        if ($index === 0) {
          $line = sprintf("%${fieldLength}s ", json_decode('"\u2002"', TRUE) . "[$delta/$total]");
        }
        else {
          $line = sprintf("%${fieldLength}s ", json_decode('"\u21B3"', TRUE));
        }
      }
      $line .= sprintf("[%6s] ", $fileItem->getBenchmark() . "s");
      if ($total !== 0 || $this->staticSuiteUtils->isRunningOnCli()) {
        $line .= "[" . $this->fileCollection->uniqueId() . "] ";
      }
      $line .= "[" . str_pad($fileItem->isPreview() ? 'preview' : 'live', 7, ' ', STR_PAD_RIGHT) . "] ";
      $line .= "[" . $fileItem->getOperation() . ' ' . str_pad($fileItem->isExecuted() ? 'executed' : 'skipped', 8, ' ', STR_PAD_RIGHT) . "] ";
      $line .= "[ID: " . $fileItem->getId() . "] ";
      $line .= $fileItem->getLabel() . " | ";
      $line .= StreamWrapperManager::getTarget($fileItem->getFilePath());
      $lines[] = $line;
    }
    return $lines;
  }

  /**
   * Formats a FileCollection using html format and returns an array.
   *
   * @param \Drupal\Core\Session\AccountProxyInterface $user
   *   The user to get the html for.
   * @param int $delta
   *   Batch delta.
   * @param int $total
   *   Total size of batch.
   *
   * @return array
   *   An array of formatted html lines.
   */
  public function getHtmlLines(AccountProxyInterface $user, int $delta = 0, int $total = 0) {
    $lines = $this->getTextLines($delta, $total);
    $scheme = $this->configFactory->get('static_export.settings')
      ->get('uri.scheme');
    foreach ($lines as $index => $line) {
      // Parse unique id link.
      if ($user->hasPermission("view static export logs") && preg_match("/ (\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}.\d{6}__\d{4}) /", $line, $matches)) {
        $fileViewerUrl = Url::fromRoute('static_export.log_viewer', ['uniqueId' => $matches[1]])
          ->toString();
        $htmlForFilePath = '<a href="' . $fileViewerUrl . '" target="_blank">' . $matches[1] . '</a>';
        $lines[$index] = str_replace($matches[1], $htmlForFilePath, $lines[$index]);
      }

      // Parse file link.
      if ($user->hasPermission("view static export files") && $index !== 0 && preg_match("/ (\S+)$/", $line, $matches)) {
        $htmlForFilePath = '<a href="' . preg_replace("/^https?:\/\//", "//", file_create_url($scheme . '://' . $matches[1])) . '" target="_blank">' . $matches[1] . '</a>';
        $lines[$index] = str_replace($matches[1], $htmlForFilePath, $lines[$index]);
      }
    }
    return $lines;
  }

}
