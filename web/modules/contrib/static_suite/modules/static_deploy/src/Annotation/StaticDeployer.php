<?php

namespace Drupal\static_deploy\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a Static Deployer plugin item annotation object.
 *
 * @see \Drupal\static_deploy\Plugin\StaticDeployerPluginManagerInterface
 * @see plugin_api
 *
 * @Annotation
 */
class StaticDeployer extends Plugin {

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

}
