<?php

namespace Drupal\static_preview_gatsby_instant\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\static_preview_gatsby_instant\Mocker\GatsbyMockerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gatsby page html resolver.
 */
class PageHtmlCustomUrlResolverController extends ControllerBase {

  /**
   * The Gatsby mocker service.
   *
   * @var \Drupal\static_preview_gatsby_instant\Mocker\GatsbyMockerInterface
   */
  protected $gatsbyMocker;

  /**
   * Constructor.
   *
   * @param \Drupal\static_preview_gatsby_instant\Mocker\GatsbyMockerInterface $gatsby_mocker
   *   Gatsby mocker service.
   */
  public function __construct(GatsbyMockerInterface $gatsby_mocker) {
    $this->gatsbyMocker = $gatsby_mocker;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('static_preview_gatsby_instant.gatsby_mocker')
    );
  }

  /**
   * Given a node url, return page data in Gatsby format.
   *
   * @param string $pagePath
   *   Page path with leading slash.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse|\Symfony\Component\HttpFoundation\Response
   *   JSON response on success, or Response with 404 code on error.
   */
  public function pageHtmlResolver(string $pagePath) {
    if ($pagePath) {
      // Restore path slashes.
      $pagePath = str_replace(":", "/", $pagePath);

      // Handle homepage case.
      if ($pagePath === "index") {
        $pagePath = '/';
      }

      $mockedPageHtml = $this->gatsbyMocker->getMockedPageHtml($pagePath);
      if ($mockedPageHtml) {
        return new Response($mockedPageHtml);
      }
    }
    // Throwing a NotFoundHttpException() caches that response, making it
    // impossible to serve the html once it's present.
    return new Response(NULL, 404, ['max-age' => 0]);
  }

}
