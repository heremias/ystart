<?php

namespace Drupal\static_export\Exporter\Data\Includes\Loader;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\static_export\Exporter\Output\Uri\UriFactoryInterface;
use Drupal\static_export\Exporter\Output\Uri\UriInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an abstract data include loader.
 */
abstract class DataIncludeLoaderPluginBase extends PluginBase implements DataIncludeLoaderPluginInterface, ContainerFactoryPluginInterface {

  /**
   * The URI factory.
   *
   * @var \Drupal\static_export\Exporter\Output\Uri\UriFactoryInterface
   */
  protected $uriFactory;

  /**
   * Constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\static_export\Exporter\Output\Uri\UriFactoryInterface $uriFactory
   *   The URI factory.
   */
  public function __construct(array $configuration,
                              string $plugin_id,
                              $plugin_definition,
                              UriFactoryInterface $uriFactory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->uriFactory = $uriFactory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get("static_export.uri_factory"),
    );
  }

  /**
   * Sanitize data to ensure it matches a specific format.
   *
   * This method could take, for instance, a pretty-printed JSON and remove all
   * its line breaks, etc.
   *
   * Since not all loaders need this method, by default it simply returns the
   * received $data string.
   *
   * @param string $data
   *   The string to be sanitized.
   *
   * @return string
   *   The sanitized data.
   */
  protected function sanitizeData(string $data): string {
    return $data;
  }

  /**
   * Tells whether this data is supported.
   *
   * Data can arrive from different sources (GraphQL, JSON:API, custom
   * exporters, etc) so we check if data is in a format this loader understand.
   *
   * @param string $data
   *   The string to be parsed.
   *
   * @return bool
   *   True if data is supported
   */
  abstract protected function isDataSupported(string $data): bool;

  /**
   * Find includes inside data.
   *
   * @param string $data
   *   Data to be parsed.
   *
   * @return array|null
   *   Array of matches, or null if nothing found.
   */
  abstract protected function findIncludes(string $data): ?array;

  /**
   * {@inheritdoc}
   */
  public function load(string $data): string {
    $data = $this->sanitizeData($data);

    // Load includes only for known data.
    if (!$this->isDataSupported($data)) {
      return $data;
    }

    return $this->parseData($data, [0 => 'ROOT']);
  }

  /**
   * Parse data and load includes.
   *
   * We use a helper function so we can manage $this->loadedIncludes in a more
   * easier way.
   *
   * @param string $data
   *   A string with includes to be parsed.
   * @param array $parents
   *   An array with the hierarchy of paths from the main data file to where an
   *   include appears.
   *
   * @return string
   *   The parsed string with includes loaded.
   */
  abstract protected function parseData(string $data, array $parents): string;

  /**
   * Loads an include and mark it as loaded to avoid repeating it.
   *
   * Internally it uses isIncludeAlreadyLoaded() and markIncludeAsLoaded().
   *
   * @param \Drupal\static_export\Exporter\Output\Uri\UriInterface $uri
   *   The include to be loaded.
   * @param array $parents
   *   An array with the hierarchy of paths from the main data file to where an
   *   include appears.
   *
   * @return string|null
   *   A string with includes loaded, or NULL if nothing found or include
   *   already loaded.
   */
  protected function loadInclude(UriInterface $uri, array $parents): ?string {
    // Avoid loading an include twice.
    if ($this->isIncludeAlreadyLoaded($uri, $parents)) {
      $includeData = NULL;
    }
    else {
      $includeData = trim(@file_get_contents($uri));
    }
    return $includeData;
  }

  /**
   * Tells whether an include is already loaded in the same parent hierarchy.
   *
   * @param \Drupal\static_export\Exporter\Output\Uri\UriInterface $uri
   *   The file to be checked.
   * @param array $parents
   *   An array with the hierarchy of paths from the main data file to where an
   *   include appears.
   *
   * @return bool
   *   True if it has been already loaded, false otherwise.
   */
  protected function isIncludeAlreadyLoaded(UriInterface $uri, array $parents): bool {
    return in_array($uri->getComposed(), $parents, TRUE);
  }

}
