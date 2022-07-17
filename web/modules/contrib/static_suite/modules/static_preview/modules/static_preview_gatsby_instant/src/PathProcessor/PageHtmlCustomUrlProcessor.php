<?php

namespace Drupal\static_preview_gatsby_instant\PathProcessor;

use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\PathProcessor\InboundPathProcessorInterface;
use Drupal\Core\Url;
use Drupal\static_export\Exporter\Output\Uri\Resolver\PagePath\PagePathUriResolverInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Processes the inbound path for custom urls defined in custom exporters.
 *
 * It checks custom exporters to determine if current path is a custom URL that
 * should be handled by a specific controller.
 */
class PageHtmlCustomUrlProcessor implements InboundPathProcessorInterface {

  /**
   * Language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The URI resolver for page paths.
   *
   * @var \Drupal\static_export\Exporter\Output\Uri\Resolver\PagePath\PagePathUriResolverInterface
   */
  protected $pagePathUriResolver;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Language\LanguageManagerInterface languageManager
   *   Language manager.
   * @param \Drupal\static_export\Exporter\Output\Uri\Resolver\PagePath\PagePathUriResolverInterface $pagePathUriResolver
   *   The URI resolver for page paths.
   */
  public function __construct(LanguageManagerInterface $languageManager, PagePathUriResolverInterface $pagePathUriResolver) {
    $this->languageManager = $languageManager;
    $this->pagePathUriResolver = $pagePathUriResolver;
  }

  /**
   * {@inheritdoc}
   */
  public function processInbound($path, Request $request) {
    $langcode = \Drupal::languageManager()->getCurrentLanguage()->getId();
    if ($this->pagePathUriResolver->isCustomPath($path, $langcode)) {
      // $path starts with a leading slash.
      $pagePath = str_replace("/", ":", $request->getPathInfo());
      // Get url without language, to avoid entering a loop.
      $languageNone = $this->languageManager->getLanguage(LanguageInterface::LANGCODE_NOT_APPLICABLE);
      $url = Url::fromRoute('static_preview_gatsby_instant.page-html-resolver', ['pagePath' => $pagePath], ['language' => $languageNone]);
      $path = (string) $url->toString(FALSE);
      return $path;
    }
    return $path;
  }

}
