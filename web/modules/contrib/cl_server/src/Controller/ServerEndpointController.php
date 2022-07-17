<?php

namespace Drupal\cl_server\Controller;

use Drupal\cl_components\Component\Component;
use Drupal\cl_components\Component\ComponentDiscovery;
use Drupal\cl_components\Exception\ComponentNotFoundException;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\PageCache\ResponsePolicy\KillSwitch;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Provides an endpoint for Storybook to query.
 *
 * @see https://github.com/storybookjs/storybook/tree/next/app/server
 */
class ServerEndpointController extends ControllerBase {

  /**
   * Kill-switch to avoid caching the page.
   *
   * @var \Drupal\Core\PageCache\ResponsePolicy\KillSwitch
   */
  private KillSwitch $cacheKillSwitch;

  /**
   * The discovery service.
   *
   * @var \Drupal\cl_components\Component\ComponentDiscovery
   */
  private ComponentDiscovery $componentDiscovery;

  /**
   * True if the inject syntax is supported.
   *
   * @var bool
   */
  private bool $supportsInject;

  /**
   * Creates an object.
   *
   * @param \Drupal\Core\PageCache\ResponsePolicy\KillSwitch $cache_kill_switch
   *   The cache kill switch.
   * @param \Drupal\cl_components\Component\ComponentDiscovery $component_discovery
   *   The component discovery.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface
   *   The module handler.
   */
  public function __construct(KillSwitch $cache_kill_switch, ComponentDiscovery $component_discovery, ModuleHandlerInterface $module_handler) {
    $this->cacheKillSwitch = $cache_kill_switch;
    $this->componentDiscovery = $component_discovery;
    $this->supportsInject = $module_handler->moduleExists('cl_inject');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    $cache_kill_switch = $container->get('page_cache_kill_switch');
    assert($cache_kill_switch instanceof KillSwitch);
    $component_discovery = $container->get(ComponentDiscovery::class);
    assert($component_discovery instanceof ComponentDiscovery);
    $module_handler = \Drupal::service('module_handler');
    assert($module_handler instanceof ModuleHandlerInterface);
    return new static($cache_kill_switch, $component_discovery, $module_handler);
  }

  /**
   * Render a Twig template from a Storybook component directory.
   */
  public function render(Request $request): array {
    $arguments = $this->getArguments($request);
    try {
      $component = $this->getComponent($request);
    }
    catch (ComponentNotFoundException $e) {
      throw new BadRequestHttpException('Invalid component', $e);
    }
    $variant = $request->query->get('_variant');
    // Storybook will not allow empty title for the story. Let's consider
    // Default to be the default.
    $variant = $variant === 'default' ? '' : $variant;
    $template = $this->generateTemplate($component, $variant ?: '', $arguments);
    $build = [
      '#type' => 'inline_template',
      '#template' => $template,
      '#context' => $arguments,
    ];
    $this->cacheKillSwitch->trigger();
    return [
      '#attached' => [
        'library' => ['cl_components/attach_behaviors'],
      ],
      '#type' => 'container',
      '#cache' => ['max-age' => 0],
      // Magic wrapper ID to pull the HTML from.
      '#attributes' => ['id' => '___cl-wrapper'],
      'component' => $build,
    ];
  }

  /**
   * Gets the arguments.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The inbound request.
   *
   * @return array
   *   The array of arguments.
   */
  private function getArguments(Request $request): array {
    $params = $request->query->get('_params');
    $json = base64_decode($params, true);
    if ($json === FALSE) {
      throw new BadRequestHttpException('Invalid component parameters');
    }
    return Json::decode($json);
  }

  /**
   * Get the component based on the request object.
   *
   * @throws \Drupal\cl_components\Exception\ComponentNotFoundException
   *   If the component cannot be found.
   */
  public function getComponent(Request $request): Component {
    $story_filename = $request->query->get('_storyFileName');
    if (!$story_filename) {
      throw new ComponentNotFoundException('Impossible to find a story with an empty story file name.');
    }
    $component = $this->componentDiscovery->findBySiblingFile($story_filename);
    if (!$component instanceof Component) {
      throw new ComponentNotFoundException('Impossible to find a component associated to the story. Make sure the story is in the same folder or in a sub0folder as the metadata.json file. Also check that metadata.json is valid.');
    }
    return $component;
  }

  /**
   * Generates a template to showcase the component with the expected blocks.
   *
   * @param \Drupal\cl_components\Component\Component $component
   *   The component.
   * @param string $variant
   *   The variant.
   * @param array $context
   *   The template context.
   *
   * @return string
   *   The generated template.
   *
   * @throws \Drupal\cl_components\Exception\TemplateNotFoundException
   */
  private function generateTemplate(Component $component, string $variant, array &$context): string {
    $metadata = $component->getMetadata();
    $component_path = $metadata->getPath();
    $template_name = $component->getTemplateName($variant);
    $template_path = $component_path . DIRECTORY_SEPARATOR . $template_name;
    $template_contents = file_get_contents($template_path);
    // If 'inject' is supported we should use it, unless there are block definitions in the template.
    $has_blocks = preg_match('@{%\s*block\s\s*([^\s.]*)\s*%}@', $template_contents);
    if (!$has_blocks && $this->supportsInject) {
      return $this->generateInjectTemplate($component, $variant, $context);
    }
    return $this->generateEmbedTemplate($template_contents, $template_path, $context);
  }

  /**
   * Generates a template to showcase the component with the expected blocks.
   *
   * @param string $template_contents
   *   The component's template definition.
   * @param string $template_path
   *   The path to the template.
   * @param array $context
   *   The template context.
   *
   * @return string
   *   The generated template.
   *
   * @throws \Exception
   */
  private function generateEmbedTemplate(string $template_contents, string $template_path, array &$context): string {
    // Let's generate the template with the correct name.
    $template = '{# This template was dynamically generated by cl_server #}' . PHP_EOL;
    $template .= "{% embed '$template_path' %}" . PHP_EOL;
    // Now find the blocks declared by the component's template.
    $matches = [];
    preg_match_all('@{%\s*block\s\s*([^\s.]*)\s*%}@', $template_contents, $matches);
    $block_names = $matches[1] ?? [];
    $block_names = array_filter($block_names);
    // Ensure the block names are declared as context variables.
    $block_values = array_intersect_key($context, array_flip($block_names));
    foreach ($block_values as $block_name => $block_value) {
      $block_build = [
        '#type' => 'inline_template',
        '#template' => $block_value,
        '#context' => $context,
      ];
      $value = \Drupal::service('renderer')->render($block_build);
      $template .= "  {% block $block_name %}" . PHP_EOL
        . "    $value" . PHP_EOL
        . "  {% endblock %}" . PHP_EOL;
      // Now that we have the data captures in blocks let's remove the props.
      unset($context[$block_name]);
    }
    $template .= '{% endembed %}' . PHP_EOL;
    return $template;
  }

  /**
   * Generates an 'inject' template for rendering a component in isolation.
   *
   * @param \Drupal\cl_components\Component\Component $component
   *   The component to render.
   * @param string $variant
   *   The component variant.
   * @param array $context
   *   The template context.
   *
   * @return string
   *   The template.
   */
  private function generateInjectTemplate(Component $component, string $variant, array $context): string {
    $component_id = $component->getId();
    $template = '{# This template was dynamically generated by cl_server #}' . PHP_EOL;
    $children = $context['children'] ?? '';
    $with = Json::encode(array_diff_key($context, array_flip(['children'])));
    $template .= empty($variant)
      ? '{% inject \'' . $component_id . '\' with ' . $with . ' %}'
      : '{% inject \'' . $component_id . '\' variant \'' . $variant . '\' with ' . $with . ' %}';

    $template .= $children;
    $template .= PHP_EOL . '{% endinject \'' . $component_id . '\' %}' . PHP_EOL;
    return $template;
  }

}
