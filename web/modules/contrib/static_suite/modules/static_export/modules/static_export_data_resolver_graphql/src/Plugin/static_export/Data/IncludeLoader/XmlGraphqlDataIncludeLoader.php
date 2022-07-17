<?php

namespace Drupal\static_export_data_resolver_graphql\Plugin\static_export\Data\IncludeLoader;

use Drupal\static_export\Exporter\Data\Includes\Loader\DataIncludeLoaderPluginBase;
use Drupal\static_export\Exporter\Output\Uri\UriInterface;

/**
 * Provides a XML data include loader for data coming from GraphQL.
 *
 * @StaticDataIncludeLoader(
 *  id = "xml-graphql",
 *  label = "XML",
 *  description = "XML data include loader for data coming from GraphQL",
 *  mimetype = "text/xml"
 * )
 */
class XmlGraphqlDataIncludeLoader extends DataIncludeLoaderPluginBase {

  protected const REGEXP_PATTERN = '/(<[^<]*(entity|config|locale|custom)Include>)([^<]+)(<\/[^<]*(entity|config|locale|custom)Include>)/i';

  /**
   * {@inheritdoc}
   */
  protected function isDataSupported(string $data): bool {
    return strpos($data, '<?xml') === 0 && preg_match(self::REGEXP_PATTERN, $data);
  }

  /**
   * Find includes inside data.
   *
   * @param string $data
   *   Data to be parsed.
   *
   * @return array|null
   *   Array of matches, or null if nothing found.
   */
  protected function findIncludes(string $data): ?array {
    preg_match_all(self::REGEXP_PATTERN, $data, $matches);
    return $matches;
  }

  /**
   * {@inheritdoc}
   *
   * Removes useless XML declaration and <response> redundant element.
   */
  protected function loadInclude(UriInterface $uri, array $parents): ?string {
    $includeData = parent::loadInclude($uri, $parents);
    if ($includeData) {
      $includeData = str_replace('<?xml version="1.0"?>', '', $includeData);
      $includeData = preg_replace("/^<data>(.+)<\/data>/", "$1", $includeData);
    }
    return $includeData;
  }

  /**
   * {@inheritdoc}
   */
  protected function parseData(string $data, array $parents): string {
    $matches = $this->findIncludes($data);
    if ($matches) {
      foreach ($matches[3] as $key => $match) {
        $uri = $this->uriFactory->create($match);
        $includeData = $this->loadInclude($uri, $parents);
        if (!empty($includeData)) {
          // Recursively load other includes.
          $includeParents = $parents;
          $includeParents[] = $uri->getComposed();
          $includeData = $this->parseData($includeData, $includeParents);
        }

        // entityInclude is a special case where its inner data is moved one
        // level up, so it goes just under an entity tag (entityInclude is only
        // available under entity tags).
        // Moving thing up can break the XML structure, so it's only done if it
        // meets some criteria.
        if ($includeData &&
          strtolower($matches[2][$key]) === 'entity' &&
          ($includeDataWithoutExtraLevels = preg_replace('/^<content>(.+)<\/content>$/', "$1", $includeData)) &&
          ($includeDataWithoutExtraLevels !== $includeData)) {
          $data = trim(str_replace($matches[0][$key], $includeDataWithoutExtraLevels, $data));
        }
        else {
          $data = trim(str_replace($matches[0][$key], $matches[1][$key] . $includeData . $matches[4][$key], $data));
        }
      }
    }
    return $data;
  }

}
