<?php

namespace Drupal\static_export\Exporter\Data\Includes\Loader;

use Drupal\static_export\Exporter\Output\Uri\UriInterface;
use Symfony\Component\Mime\MimeTypeGuesserInterface;

/**
 * The data include loader.
 */
class DataIncludeLoader implements DataIncludeLoaderInterface {

  /**
   * The mime type guesser.
   *
   * @var \Symfony\Component\HttpFoundation\File\MimeType\MimeTypeGuesserInterface
   */
  protected $mimeTypeGuesser;

  /**
   * The data include loader plugin manager.
   *
   * @var \Drupal\static_export\Exporter\Data\Includes\Loader\DataIncludeLoaderPluginManagerInterface
   */
  protected $dataIncludeLoaderPluginManager;

  /**
   * DataIncludeLoader constructor.
   *
   * @param \Symfony\Component\Mime\MimeTypeGuesserInterface $mimeTypeGuesser
   *   The mime type guesser.
   * @param \Drupal\static_export\Exporter\Data\Includes\Loader\DataIncludeLoaderPluginManagerInterface $dataIncludeLoaderPluginManager
   *   The data include loader plugin manager.
   */
  public function __construct(MimeTypeGuesserInterface $mimeTypeGuesser, DataIncludeLoaderPluginManagerInterface $dataIncludeLoaderPluginManager) {
    $this->mimeTypeGuesser = $mimeTypeGuesser;
    $this->dataIncludeLoaderPluginManager = $dataIncludeLoaderPluginManager;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function loadUri(UriInterface $uri, string $mimeType = NULL): string {
    if (!$mimeType) {
      $mimeType = $this->mimeTypeGuesser->guess($uri);
    }
    $uriContents = @file_get_contents($uri);
    return $this->load($uriContents, $mimeType);
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function loadString(string $contents, string $mimeType = NULL): string {
    if (!$mimeType) {
      $mimeType = $this->mimeTypeGuesser->guess($contents);
    }
    return $this->load($contents, $mimeType);
  }

  /**
   * Load data includes for a string and a known mime type.
   *
   * @param string $contents
   *   String to be parsed.
   * @param string $mimeType
   *   Content mime type.
   *
   * @return string
   *   The processed string.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  protected function load(string $contents, string $mimeType): string {
    $definitionsByMimetype = $this->dataIncludeLoaderPluginManager->getDefinitionsByMimeType($mimeType);
    foreach ($definitionsByMimetype as $definitionByMimetype) {
      $includeLoader = $this->dataIncludeLoaderPluginManager->getInstance(['plugin_id' => $definitionByMimetype['id']]);
      $processedContents = $includeLoader->load($contents);
      if ($processedContents !== $contents) {
        return $processedContents;
      }
    }
    return $contents;
  }

}
