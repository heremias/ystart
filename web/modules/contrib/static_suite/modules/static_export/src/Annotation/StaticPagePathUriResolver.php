<?php

namespace Drupal\static_export\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a URI resolver annotation for page paths in Static Export.
 *
 * @Annotation
 */
class StaticPagePathUriResolver extends Plugin {

  /**
   * The plugin ID. A lowercase string.
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
   * The type of the plugin. One of "entity" or "custom".
   *
   * @var string
   */
  public $type;

  /**
   * The weight of the plugin, that alters the order of execution.
   *
   * Plugins with negative values are executed first. -10 is executed before -5,
   * then 0, them 5, and so on.
   *
   * @var int
   */
  public $weight = 0;

}
