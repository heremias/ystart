<?php

namespace Drupal\static_preview_gatsby_instant\Mocker;

/**
 * An interface to provide Gatsby with mocked data, components, etc.
 */
interface GatsbyMockerInterface {

  /**
   * Given a page path or alias, return mocked page data for preview component.
   *
   * @param string $pagePath
   *   Page path with leading slash.
   *
   * @return array|null
   *   The page data in array format, ready to be returned in a JsonResponse.
   */
  public function getMockedPageData(string $pagePath): ?array;

  /**
   * Given a page path or alias, return mocked page html for preview component.
   *
   * @param string $pagePath
   *   Page path or alias to get data for.
   *
   * @return string|null
   *   The mocked html.
   */
  public function getMockedPageHtml(string $pagePath): ?string;

}
