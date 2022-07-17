<?php

namespace Drupal\static_export\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\static_export\Exporter\Output\Uri\Resolver\PagePath\PagePathUriResolverInterface;
use Drupal\static_export\Exporter\Type\Entity\Output\Uri\Resolver\EntityExporterUriResolverInterface;
use Drupal\static_suite\Entity\EntityUtilsInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Throwable;

/**
 * Defines a controller to resolve uris of exported files.
 */
class ExportedUriResolverController extends ControllerBase {

  /**
   * Entity utils.
   *
   * @var \Drupal\static_suite\Entity\EntityUtilsInterface
   */
  protected $entityUtils;

  /**
   * Entity exporter path resolver.
   *
   * @var \Drupal\static_export\Exporter\Type\Entity\Output\Uri\Resolver\EntityExporterUriResolverInterface
   */
  protected $entityExporterUriResolver;

  /**
   * The URI resolver for page paths.
   *
   * @var \Drupal\static_export\Exporter\Output\Uri\Resolver\PagePath\PagePathUriResolverInterface
   */
  protected $pagePathUriResolver;

  /**
   * Resolver controller constructor.
   *
   * @param \Drupal\static_suite\Entity\EntityUtilsInterface $entityUtils
   *   Entity utils.
   * @param \Drupal\static_export\Exporter\Type\Entity\Output\Uri\Resolver\EntityExporterUriResolverInterface $entityExporterUriResolver
   *   Exported URI resolver.
   * @param \Drupal\static_export\Exporter\Output\Uri\Resolver\PagePath\PagePathUriResolverInterface $pagePathUriResolver
   *   The URI resolver for page paths.
   */
  public function __construct(
    EntityUtilsInterface $entityUtils,
    EntityExporterUriResolverInterface $entityExporterUriResolver,
    PagePathUriResolverInterface $pagePathUriResolver
  ) {
    $this->entityUtils = $entityUtils;
    $this->entityExporterUriResolver = $entityExporterUriResolver;
    $this->pagePathUriResolver = $pagePathUriResolver;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('static_suite.entity_utils'),
      $container->get('static_export.entity_exporter_uri_resolver'),
      $container->get('static_export.page_path_uri_resolver')
    );
  }

  /**
   * Given an entity type id and an entity id, return its URI.
   *
   * URI scheme is hidden when working from Drupal, because that scheme is
   * automatically set from configuration data. But, those controllers are
   * meant to be accessed from outside Drupal, so we make the scheme visible
   * to make it usable.
   *
   * @param string $entityTypeId
   *   Entity type id to search for.
   * @param string $entityId
   *   Entity id to search for.
   * @param string|null $langcode
   *   Optional language id.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   A JSON response.
   */
  public function getExportedUriByEntityData(string $entityTypeId, string $entityId, string $langcode = NULL): JsonResponse {
    try {
      $entity = $this->entityUtils->loadEntity($entityTypeId, $entityId, $langcode);
    }
    catch (Throwable $e) {
      $entity = NULL;
    }
    $uri = $entity ? $this->entityExporterUriResolver->setEntity($entity)
      ->getMainUri() : NULL;
    return new JsonResponse(['uri' => $uri ? $uri->getComposed() : NULL]);
  }

  /**
   * Given a page path or alias, return its URI.
   *
   * URI scheme is hidden when working from Drupal, because that scheme is
   * automatically set from configuration data. But, those controllers are
   * meant to be accessed from outside Drupal, so we make the scheme visible
   * to make it usable.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   A JSON response.
   */
  public function getExportedUriByPagePath(Request $request): JsonResponse {
    $pagePath = $request->query->get('page-path');
    $langcode = $request->query->get('langcode');
    $uri = $pagePath ? $this->pagePathUriResolver->resolve($pagePath, $langcode) : NULL;
    return new JsonResponse(['uri' => $uri ? $uri->getComposed() : NULL]);
  }

}
