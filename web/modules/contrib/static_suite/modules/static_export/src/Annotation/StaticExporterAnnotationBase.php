<?php

namespace Drupal\static_export\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a base annotation for Static Exporter plugins.
 *
 * Static exporter plugins share the same annotation structure (id, label,
 * description) so all of them extend from this abstract class.
 */
abstract class StaticExporterAnnotationBase extends Plugin {

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

}
