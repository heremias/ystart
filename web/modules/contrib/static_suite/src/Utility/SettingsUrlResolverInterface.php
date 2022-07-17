<?php

namespace Drupal\static_suite\Utility;

use Drupal\Core\Url;

/**
 * Interface for resolving the URL of settings for plugins, modules, etc.
 */
interface SettingsUrlResolverInterface {

  /**
   * Sets the route prefix to be checked.
   *
   * @param string $prefix
   *   The route prefix (e.g.- "static_suite.plugins.").
   *
   * @return SettingsUrlResolverInterface
   *   The resolver instance.
   */
  public function setRoutePrefix(string $prefix): SettingsUrlResolverInterface;

  /**
   * Sets the route key to be checked.
   *
   * @param string $key
   *   The route key (e.g.- "my-plugin").
   *
   * @return SettingsUrlResolverInterface
   *   The resolver instance.
   */
  public function setRouteKey(string $key): SettingsUrlResolverInterface;

  /**
   * Sets the route suffix to be checked.
   *
   * @param string $suffix
   *   The route suffix (e.g.- ".settings").
   *
   * @return SettingsUrlResolverInterface
   *   The resolver instance.
   */
  public function setRouteSuffix(string $suffix): SettingsUrlResolverInterface;

  /**
   * Sets the module name to be checked.
   *
   * @param string $module
   *   The module name.
   *
   * @return SettingsUrlResolverInterface
   *   The resolver instance.
   */
  public function setModule(string $module): SettingsUrlResolverInterface;

  /**
   * Sets the class name to be checked.
   *
   * @param string $class
   *   The class name.
   *
   * @return SettingsUrlResolverInterface
   *   The resolver instance.
   */
  public function setClass(string $class): SettingsUrlResolverInterface;

  /**
   * Resolve the settings URL.
   *
   * @return URL|null
   *   A URL object, or null if nothing found.
   */
  public function resolve(): ?Url;

  /**
   * Reset the resolver and initialize all its values.
   *
   * @return SettingsUrlResolverInterface
   *   The resolver instance.
   */
  public function reset(): SettingsUrlResolverInterface;

}
