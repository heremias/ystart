<?php

namespace Drupal\static_build\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a Static Builder plugin item annotation object.
 *
 * @see \Drupal\static_build\Plugin\StaticBuilderPluginManagerInterface
 * @see plugin_api
 *
 * @Annotation
 */
class StaticBuilder extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The label of the plugin.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $label;

  /**
   * The description of the plugin.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $description;

  /**
   * Where is this plugin meant to be run: local or cloud.
   *
   * @var string
   */
  public $host;

}
