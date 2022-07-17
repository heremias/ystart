<?php

namespace Drupal\static_export\File\MimeType;

use Drupal\static_export\Exporter\Output\Formatter\OutputFormatterPluginManagerInterface;
use Symfony\Component\Mime\MimeTypeGuesserInterface;

/**
 * Guess the MIME type of a file using StaticOutputFormatter annotation.
 */
class OutputFormatterMimeTypeGuesser implements MimeTypeGuesserInterface {

  /**
   * The output formatter manager.
   *
   * @var \Drupal\static_export\Exporter\Output\Formatter\OutputFormatterPluginManagerInterface
   */
  protected $outputFormatterManager;

  /**
   * Constructs a new OutputFormatterMimeTypeGuesser.
   *
   * @param \Drupal\static_export\Exporter\Output\Formatter\OutputFormatterPluginManagerInterface $outputFormatterManager
   *   The output formatter manager.
   */
  public function __construct(OutputFormatterPluginManagerInterface $outputFormatterManager) {
    $this->outputFormatterManager = $outputFormatterManager;
  }

  /**
   * {@inheritdoc}
   */
  public function isGuesserSupported(): bool {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function guessMimeType($path): ?string {
    $extension = pathinfo($path, PATHINFO_EXTENSION);
    $outputFormatterDefinitions = $this->outputFormatterManager->getDefinitions();
    foreach ($outputFormatterDefinitions as $outputFormatterDefinition) {
      if ($outputFormatterDefinition['extension'] === $extension) {
        return $outputFormatterDefinition['mimetype'];
      }
    }

    return NULL;
  }

}
