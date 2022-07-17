<?php

namespace Drupal\static_preview_gatsby_instant\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\static_preview_gatsby_instant\Mocker\GatsbyMockerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gatsby page data resolver.
 */
class PageDataResolverController extends ControllerBase {

  /**
   * The Gatsby mocker service.
   *
   * @var \Drupal\static_preview_gatsby_instant\Mocker\GatsbyMockerInterface
   */
  protected $gatsbyMocker;

  /**
   * Constructor.
   *
   * @param \Drupal\static_preview_gatsby_instant\Mocker\GatsbyMockerInterface $gatsbyMocker
   *   Gatsby mocker service.
   */
  public function __construct(GatsbyMockerInterface $gatsbyMocker) {
    $this->gatsbyMocker = $gatsbyMocker;
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
   * Given a page path, return page data in Gatsby format.
   *
   * @param string $pagePath
   *   Page path with leading slash.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse|\Symfony\Component\HttpFoundation\Response
   *   JSON response on success, or Response with 404 code on error.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\static_suite\StaticSuiteException
   * @throws \JsonException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function pageDataResolver(string $pagePath) {
    if ($pagePath) {
      // Restore path slashes.
      $pagePath = str_replace(":", "/", $pagePath);

      // Handle homepage case.
      if ($pagePath === "/index") {
        $pagePath = '/';
      }

      $mockedPageData = $this->gatsbyMocker->getMockedPageData($pagePath);
      if (is_array($mockedPageData)) {
        return new JsonResponse($mockedPageData);
      }
    }
    // Throwing a NotFoundHttpException() caches that response, making it
    // impossible to serve the JSON file once it's present.
    return new Response(NULL, 404, ['max-age' => 0]);
  }

}
