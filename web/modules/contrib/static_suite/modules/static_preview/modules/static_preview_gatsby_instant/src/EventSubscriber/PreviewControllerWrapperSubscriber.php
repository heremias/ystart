<?php

namespace Drupal\static_preview_gatsby_instant\EventSubscriber;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\node\Controller\NodeViewController;
use Drupal\static_build\Plugin\StaticBuilderPluginManagerInterface;
use Drupal\static_export\Entity\ExportableEntityManagerInterface;
use Drupal\static_preview\Event\StaticPreviewEvent;
use Drupal\static_preview\Event\StaticPreviewEvents;
use Drupal\static_preview_gatsby_instant\Mocker\GatsbyMockerInterface;
use ReflectionFunction;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Controller\ArgumentResolverInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Subscriber that wraps controllers to handle Gatsby instant preview.
 */
class PreviewControllerWrapperSubscriber implements EventSubscriberInterface {

  /**
   * The argument resolver.
   *
   * @var \Symfony\Component\HttpKernel\Controller\ArgumentResolverInterface
   */
  protected $argumentResolver;

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
   * Gatsby mocker service.
   *
   * @var \Drupal\static_preview_gatsby_instant\Mocker\GatsbyMockerInterface
   */
  protected $gatsbyMocker;

  /**
   * The static builder plugin manager.
   *
   * @var \Drupal\static_build\Plugin\StaticBuilderPluginManagerInterface
   */
  protected $staticBuilderPluginManager;

  /**
   * Constructs a new ControllerWrapperSubscriber instance.
   *
   * @param \Symfony\Component\HttpKernel\Controller\ArgumentResolverInterface $argumentResolver
   *   The argument resolver.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   The event dispatcher.
   * @param \Drupal\static_preview_gatsby_instant\Mocker\GatsbyMockerInterface $gatsbyMocker
   *   Gatsby mocker service.
   * @param \Drupal\static_build\Plugin\StaticBuilderPluginManagerInterface $staticBuilderPluginManager
   *   The static builder plugin manager.
   * @param \Drupal\static_export\Entity\ExportableEntityManagerInterface $exportableEntityManager
   *   The exportable entity manager.
   */
  public function __construct(
    ArgumentResolverInterface $argumentResolver,
    EventDispatcherInterface $eventDispatcher,
    GatsbyMockerInterface $gatsbyMocker,
    StaticBuilderPluginManagerInterface $staticBuilderPluginManager,
    ExportableEntityManagerInterface $exportableEntityManager
  ) {
    $this->argumentResolver = $argumentResolver;
    $this->eventDispatcher = $eventDispatcher;
    $this->gatsbyMocker = $gatsbyMocker;
    $this->staticBuilderPluginManager = $staticBuilderPluginManager;
    $this->exportableEntityManager = $exportableEntityManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[KernelEvents::CONTROLLER][] = ['onController'];

    return $events;
  }

  /**
   * Wraps a controller execution in a preview handler.
   *
   * @param \Symfony\Component\HttpKernel\Event\ControllerEvent $event
   *   The controller event.
   */
  public function onController(ControllerEvent $event): void {
    $controller = $event->getController();

    // See \Symfony\Component\HttpKernel\HttpKernel::handleRaw().
    $arguments = $this->argumentResolver->getArguments($event->getRequest(), $controller);

    $request = $event->getRequest();

    $event->setController(function () use ($controller, $arguments, $request) {
      return $this->wrapControllerExecutionInPreviewHandler($controller, $arguments, $request);
    });
  }

  /**
   * Looks up for an ExportableEntity and return its mocked page HTML.
   *
   * If using Reflection creates any problem, there is a slightly less
   * efficient
   * way of getting arguments, using $request->getPathInfo() and
   * EntityUtilsInterface::getEntityDataByPagePath(), and then loading the
   * entity
   * (that is already cached)
   *
   * @param callable $controller
   *   The controller to execute.
   * @param array $arguments
   *   The arguments to pass to the controller.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return mixed
   *   The return value of the controller.
   *
   * @throws \ReflectionException
   */
  protected function wrapControllerExecutionInPreviewHandler(callable $controller, array $arguments, Request $request) {
    $route = $request->get('_route');
    $routeParts = explode('.', $route);
    if (count($routeParts) === 3 && $routeParts[0] === 'entity' && $routeParts[2] === 'canonical') {
      $isStatifiedPage = FALSE;
      $isPreviewable = TRUE;
      $reflectionFunction = new ReflectionFunction($controller);
      $staticVariables = $reflectionFunction->getStaticVariables();
      $controller = $staticVariables['controller'];
      $arguments = $staticVariables['arguments'];
      if (is_array($controller) && is_array($arguments)) {
        // When the controller is NodeViewController::view(), we ensure that
        // $view_mode parameter is "full".
        if (($controller[0] instanceof NodeViewController && $controller[1] === 'view' && $arguments[1] === 'full') || !$controller[0] instanceof NodeViewController) {
          $argumentsWithEntityInside = $this->chooseArgumentsArray($arguments);
          foreach ($argumentsWithEntityInside as $argument) {
            if ($argument instanceof EntityInterface) {
              $exportableEntity = $this->exportableEntityManager->getExportableEntity($argument);
              if ($exportableEntity) {
                $isStatifiedPage = $exportableEntity->getIsStatifiedPage();
                // Check if this exportable entity can be previewed.
                $event = new StaticPreviewEvent($argument);
                $this->eventDispatcher->dispatch($event, StaticPreviewEvents::PRE_RENDER);
                $isPreviewable = $event->isPreviewable();
              }
              break;
            }
          }
        }
      }

      if ($isStatifiedPage && $isPreviewable) {
        $mockedPageHtml = $this->gatsbyMocker->getMockedPageHtml($request->getPathInfo());
        if ($mockedPageHtml) {
          return new Response($mockedPageHtml);
        }
      }
    }

    return call_user_func_array($controller, $arguments);
  }

  /**
   * Choose which array contains the arguments with an entity inside.
   *
   * @param array $arguments
   *   The arguments to pass to the controller.
   *
   * @return array
   *   The array where an entity is expected to be found.
   */
  protected function chooseArgumentsArray(array $arguments): array {
    foreach ($arguments as $argument) {
      if ($argument instanceof RouteMatchInterface) {
        $parameters = $argument->getParameters()->all();
        if (is_array($parameters)) {
          return $parameters;
        }
      }
    }
    return $arguments;
  }

}
