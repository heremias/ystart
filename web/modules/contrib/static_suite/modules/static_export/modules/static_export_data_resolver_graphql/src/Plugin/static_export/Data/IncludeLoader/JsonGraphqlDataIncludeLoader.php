<?php

namespace Drupal\static_export_data_resolver_graphql\Plugin\static_export\Data\IncludeLoader;

use Drupal\static_export\Exporter\Data\Includes\Loader\DataIncludeLoaderPluginBase;
use Exception;

/**
 * Provides a JSON data include loader for data coming from GraphQL.
 *
 * @StaticDataIncludeLoader(
 *  id = "json-graphql",
 *  label = "JSON",
 *  description = "JSON data include loader for data coming from GraphQL",
 *  mimetype = "application/json"
 * )
 */
class JsonGraphqlDataIncludeLoader extends DataIncludeLoaderPluginBase {

  protected const REGEXP_PATTERN = '/"([^"]*(entity|config|locale|custom))Include"\s*:\s*"([^"]+)"/i';

  /**
   * {@inheritdoc}
   *
   * Check if data is pretty printed, with multiple line breaks. If true,
   * re-encode it without pretty-printing it.
   */
  protected function sanitizeData(string $data): string {
    if (substr_count($data, "\n") > 1) {
      try {
        $dataArray = json_decode($data, TRUE, 512, JSON_THROW_ON_ERROR);
        $data = json_encode($dataArray, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      }
      catch (Exception $e) {
        @trigger_error('Error while sanitizing data in JSON GraphQL data include loader: ' . $e->getMessage(), E_USER_WARNING);
      }
    }
    return $data;
  }

  /**
   * {@inheritdoc}
   */
  protected function isDataSupported(string $data): bool {
    return strpos($data, '{"data":{') === 0 && preg_match(self::REGEXP_PATTERN, $data);
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

        // entityInclude is a special case where its inner data is moved several
        // levels up, so it goes just under an entity tag (entityInclude is only
        // available under entity tags).
        // Moving thing up can break the JSON structure, so it's only done if it
        // meets some criteria.
        if ($includeData &&
          strtolower($matches[2][$key]) === 'entity' &&
          ($includeDataWithoutExtraLevels = preg_replace('/^{"data":{"content":{(.+)}}}$/', "$1", $includeData)) &&
          ($includeDataWithoutExtraLevels !== $includeData)) {
          $data = trim(str_replace($matches[0][$key], $includeDataWithoutExtraLevels, $data));
        }
        else {
          [$includeKey] = explode(":", $matches[0][$key]);
          $includeKey = preg_replace('/Include"$/', '"', $includeKey);
          $data = trim(str_replace($matches[0][$key], $includeKey . ':' . ($includeData ?: "null"), $data));
        }
      }
    }
    return $data;
  }

}
