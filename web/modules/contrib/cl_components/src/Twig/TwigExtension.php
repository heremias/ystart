<?php

namespace Drupal\cl_components\Twig;

use Drupal\cl_components\Component\ComponentDiscovery;
use Drupal\cl_components\Exception\TemplateNotFoundException;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * The twig extension so Drupal can recognize the new code.
 */
class TwigExtension extends AbstractExtension {

  /**
   * The discovery.
   *
   * @var \Drupal\cl_components\Component\ComponentDiscovery
   */
  private ComponentDiscovery $discovery;

  /**
   * Creates TwigExtension.
   *
   * @param \Drupal\cl_components\Component\ComponentDiscovery $discovery
   *   The component discovery.
   */
  public function __construct(ComponentDiscovery $discovery) {
    $this->discovery = $discovery;
  }

  /**
   * {@inheritdoc}
   */
  public function getFunctions() {
    return [
      new TwigFunction(
        'cl_components_additional_context',
        [$this, 'addAdditionalContext'],
        ['needs_context' => TRUE]
      ),
    ];
  }

  /**
   * Appends additional context to the template based on the template id.
   *
   * @param array &$context
   *   The context.
   * @param string $component_id
   *   The component ID.
   * @param string $variant
   *   The variant.
   *
   * @throws \Drupal\cl_components\Exception\TemplateNotFoundException|\Drupal\cl_components\Exception\ComponentNotFoundException
   */
  public function addAdditionalContext(array &$context, string $component_id, string $variant, int $debug) {
    $component = $this->discovery->find($component_id);
    if (!empty($variant) && !in_array($variant, $component->getVariants())) {
      $message = sprintf(
        'Unable to render variant "%s". This variant is not declared in the metadata.json for this component.',
        $variant
      );
      throw new TemplateNotFoundException($message);
    }
    $context = array_merge(
      $context,
      ['debugMode' => (bool) $debug],
      $component->additionalRenderContext($variant)
    );
  }

}
