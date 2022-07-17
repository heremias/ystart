<?php

namespace Drupal\static_suite\Utility;

use Drupal\Core\Extension\InfoParserInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\Core\Url;

/**
 * Class for resolving the URL of settings for plugins, modules, etc.
 */
class SettingsUrlResolver implements SettingsUrlResolverInterface {

  /**
   * The route provider.
   *
   * @var \Drupal\Core\Routing\RouteProviderInterface
   */
  protected RouteProviderInterface $routeProvider;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * The info parser service.
   *
   * @var \Drupal\Core\Extension\InfoParserInterface
   */
  protected InfoParserInterface $infoParser;

  /**
   * The route prefix (e.g.- "static_suite.plugins.").
   *
   * @var string
   */
  protected ?string $routePrefix = NULL;

  /**
   * The route key (e.g.- "my-plugin").
   *
   * @var string
   */
  protected ?string $routeKey = NULL;

  /**
   * The route suffix (e.g.- ".settings").
   *
   * @var string
   */
  protected ?string $routeSuffix = NULL;

  /**
   * The module name.
   *
   * @var string
   */
  protected ?string $module = NULL;

  /**
   * The class name.
   *
   * @var string
   */
  protected ?string $class = NULL;

  /**
   * PagePathUriResolver constructor.
   *
   * @param \Drupal\Core\Routing\RouteProviderInterface $routeProvider
   *   The route provider.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   * @param \Drupal\Core\Extension\InfoParserInterface $infoParser
   *   The info parser service.
   */
  public function __construct(RouteProviderInterface $routeProvider, ModuleHandlerInterface $moduleHandler, InfoParserInterface $infoParser) {
    $this->routeProvider = $routeProvider;
    $this->moduleHandler = $moduleHandler;
    $this->infoParser = $infoParser;
  }

  /**
   * {@inheritdoc}
   */
  public function setRoutePrefix(string $prefix): SettingsUrlResolverInterface {
    $this->routePrefix = $prefix;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setRouteKey(string $key): SettingsUrlResolverInterface {
    $this->routeKey = $key;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setRouteSuffix(string $suffix): SettingsUrlResolverInterface {
    $this->routeSuffix = $suffix;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setModule(string $module): SettingsUrlResolverInterface {
    $this->module = $module;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setClass(string $class): SettingsUrlResolverInterface {
    $this->class = $class;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function resolve(): ?Url {
    $url = NULL;

    // First check a manually crafted route.
    $route = $this->routePrefix . str_replace('-', '_', $this->routeKey) . $this->routeSuffix ?: '.settings';
    if (count($this->routeProvider->getRoutesByNames([$route])) === 1) {
      $url = Url::fromRoute($route);
    }
    else {
      // Check if settings are provided by a module.
      $moduleName = $this->module;
      if (!$moduleName && $this->class && $classParts = explode('\\', $this->class)) {
        $moduleName = $classParts[1] ?? NULL;
      }

      if ($moduleName && $this->moduleHandler->moduleExists($moduleName)) {
        $moduleInfoPathname = $this->moduleHandler->getModule($moduleName)
          ->getPathname();
        $moduleInfo = $this->infoParser->parse($moduleInfoPathname);
        if (!empty($moduleInfo['configure'])) {
          $url = Url::fromRoute($moduleInfo['configure']);
        }
      }
    }

    $this->reset();

    return $url;
  }

  /**
   * {@inheritdoc}
   */
  public function reset(): SettingsUrlResolverInterface {
    $this->routePrefix = NULL;
    $this->routeKey = NULL;
    $this->routeSuffix = NULL;
    $this->module = NULL;
    $this->class = NULL;
    return $this;
  }

}
