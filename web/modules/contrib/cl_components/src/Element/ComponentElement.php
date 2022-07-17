<?php

namespace Drupal\cl_components\Element;

use Drupal\cl_components\Component\ComponentDiscovery;
use Drupal\Core\Render\Element\RenderElement;

/**
 * Provides a Storybook component render element.
 *
 * Properties:
 * - #component: The machine name of the component.
 * - #variant: (optional) The variant to be used for the component.
 *
 * Usage Example:
 *
 * @code
 * $build['component'] = array(
 *   '#type' => 'cl_component',
 *   '#component' => 'button',
 *   '#variant' => 'secondary',
 * );
 * @endcode
 *
 * @see \Drupal\Core\Render\Element\Textarea
 *
 * @RenderElement("cl_component")
 */
class ComponentElement extends RenderElement {

  /**
   * Expands a cl_component into an inline template with an attachment.
   *
   * @param array $element
   *   The element to process. See main class documentation for properties.
   *
   * @return array
   *   The form element.
   *
   * @throws \Drupal\cl_components\Exception\ComponentNotFoundException
   * @throws \Drupal\cl_components\Exception\TemplateNotFoundException
   */
  public static function preRenderComponent(array $element): array {
    $variant = $element['#variant'] ?? '';
    $component = \Drupal::service(ComponentDiscovery::class)->find($element['#component']);
    $template_path = $component->getMetadata()->getPath() . DIRECTORY_SEPARATOR . $component->getTemplateName($variant);
    $inline_template = '{% include "' . $template_path . '" %}';
    $element['inline-template'] = [
      '#type' => 'inline_template',
      '#template' => $inline_template,
      '#context' => $element['#context'] ?? [],
    ];
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function getInfo(): array {
    $class = static::class;
    return [
      '#pre_render' => [
        [$class, 'preRenderComponent'],
      ],
      '#component' => '',
      '#variant' => '',
      '#context' => [],
    ];
  }

}
