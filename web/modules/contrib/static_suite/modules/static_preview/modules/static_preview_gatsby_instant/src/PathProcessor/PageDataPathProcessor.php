<?php

namespace Drupal\static_preview_gatsby_instant\PathProcessor;

use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\PathProcessor\InboundPathProcessorInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\Request;

/**
 * Processes the inbound path for page-data urls from Gatsby.
 *
 * Since queries with slashes cannot be passed to this path, it replaces
 * slashes with ":", which are later decoded by the controller.
 */
class PageDataPathProcessor implements InboundPathProcessorInterface {

  /**
   * Language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Language\LanguageManagerInterface languageManager
   *   Language manager.
   */
  public function __construct(LanguageManagerInterface $languageManager) {
    $this->languageManager = $languageManager;
  }

  /**
   * {@inheritdoc}
   */
  public function processInbound($path, Request $request) {
    if (preg_match("#^/page-data/(.*)/page-data.json$#", $path, $matches)) {
      // Add leading slash to $pagePath.
      $pagePath = str_replace("/", ":", "/" . $matches[1]);
      // Get url without language, to avoid entering a loop.
      $languageNone = $this->languageManager->getLanguage(LanguageInterface::LANGCODE_NOT_APPLICABLE);
      $url = Url::fromRoute('static_preview_gatsby_instant.page-data-resolver', ['pagePath' => $pagePath], ['language' => $languageNone]);
      $path = (string) $url->toString(FALSE);
      return $path;
    }
    return $path;
  }

}
