<?php

namespace Drupal\static_preview_gatsby_instant\Controller;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\Core\Render\RendererInterface;
use Drupal\node\Controller\NodePreviewController as BaseNodePreviewController;
use Drupal\path_alias\AliasManagerInterface;
use Drupal\static_export\Entity\ExportableEntityManagerInterface;
use Drupal\static_preview\Event\StaticPreviewEvent;
use Drupal\static_preview\Event\StaticPreviewEvents;
use Drupal\static_preview_gatsby_instant\Mocker\GatsbyMockerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Defines a controller to render a single node in preview.
 */
class NodePreviewController extends BaseNodePreviewController {

  /**
   * The path alias manager.
   *
   * @var \Drupal\path_alias\AliasManagerInterface
   */
  protected $pathAliasManager;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcher
   */
  protected $eventDispatcher;

  /**
   * The exportable entity manager.
   *
   * @var \Drupal\static_export\Entity\ExportableEntityManagerInterface
   */
  protected $exportableEntityManager;

  /**
   * The Gatsby mocker service.
   *
   * @var \Drupal\static_preview_gatsby_instant\Mocker\GatsbyMockerInterface
   */
  protected $gatsbyMocker;

  /**
   * The entity repository service.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * The current path for the current request.
   *
   * @var \Drupal\Core\Path\CurrentPathStack
   */
  protected $currentPath;

  /**
   * Creates an NodeViewController object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository.º.
   * @param \Drupal\path_alias\AliasManagerInterface $pathAliasManager
   *   The path alias manager.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   The event dispatcher.
   * @param \Drupal\static_export\Entity\ExportableEntityManagerInterface $exportableEntityManager
   *   The exportable entity manager.
   * @param \Drupal\static_preview_gatsby_instant\Mocker\GatsbyMockerInterface $gatsbyMocker
   *   The Gatsby mocker service.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    RendererInterface $renderer,
    EntityRepositoryInterface $entity_repository,
    AliasManagerInterface $pathAliasManager,
    CurrentPathStack $currentPath,
    EventDispatcherInterface $eventDispatcher,
    ExportableEntityManagerInterface $exportableEntityManager,
    GatsbyMockerInterface $gatsbyMocker
  ) {
    parent::__construct($entity_type_manager, $renderer, $entity_repository);
    $this->pathAliasManager = $pathAliasManager;
    $this->exportableEntityManager = $exportableEntityManager;
    $this->gatsbyMocker = $gatsbyMocker;
    $this->currentPath = $currentPath;
    $this->eventDispatcher = $eventDispatcher;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('renderer'),
      $container->get('entity.repository'),
      $container->get('path_alias.manager'),
      $container->get("path.current"),
      $container->get("event_dispatcher"),
      $container->get('static_export.exportable_entity_manager'),
      $container->get('static_preview_gatsby_instant.gatsby_mocker'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function view(EntityInterface $node_preview, $view_mode_id = 'full', $langcode = NULL) {
    $exportableEntity = $this->exportableEntityManager->getExportableEntity($node_preview);

    $isStatifiedPage = FALSE;
    $isPreviewable = TRUE;
    if ($exportableEntity) {
      $isStatifiedPage = $exportableEntity->getIsStatifiedPage();
      $event = new StaticPreviewEvent($node_preview);
      $this->eventDispatcher->dispatch($event, StaticPreviewEvents::PRE_RENDER);
      $isPreviewable = $event->isPreviewable();
    }

    if ($isStatifiedPage && $isPreviewable) {
      $pagePath = $this->currentPath->getPath();
      $mockedPageHtml = $this->gatsbyMocker->getMockedPageHtml($pagePath);
      if ($mockedPageHtml) {
        return new Response($mockedPageHtml);
      }
    }
    return parent::view($node_preview, $view_mode_id, $langcode);
  }

}
