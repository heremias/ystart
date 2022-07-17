<?php

namespace Drupal\cl_components\Component;

use Drupal\cl_components\Exception\ComponentNotFoundException;
use Drupal\cl_components\Exception\TemplateNotFoundException;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Template\Loader\FilesystemLoader;
use Twig\Error\LoaderError;
use Twig\Source;

/**
 * Lets you load templates using the component ID.
 */
class ComponentLoader extends FilesystemLoader {

  /**
   * The component discovery service.
   *
   * @var \Drupal\cl_components\Component\ComponentDiscovery
   */
  protected ComponentDiscovery $componentDiscovery;

  /**
   * Checks if Twig is in debug mode.
   *
   * @var bool
   */
  private bool $debug;

  /**
   * Additional debugging tools.
   *
   * @var bool
   */
  private bool $additionalDebug;

  /**
   * Cache for parsed ID and variants.
   *
   * @var array
   */
  private array $idVariantCache = [];

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  private RendererInterface $renderer;

  /**
   * Constructs a new ComponentLoader object.
   *
   * @param \Drupal\cl_components\Component\ComponentDiscovery $component_discovery
   *   The component discovery.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\Extension\ThemeHandlerInterface $theme_handler
   *   The theme handler.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   * @param ?array $twig_config
   *   The twig configuration.
   */
  public function __construct(ComponentDiscovery $component_discovery, ConfigFactoryInterface $config_factory, ModuleHandlerInterface $module_handler, ThemeHandlerInterface $theme_handler, RendererInterface $renderer, ?array $twig_config) {
    $this->componentDiscovery = $component_discovery;
    $this->additionalDebug = (bool) $config_factory->get('cl_components.settings')
      ->get('debug');
    $twig_config = $twig_config ?: [];
    $this->debug = (bool) ($twig_config['debug'] ?? FALSE);
    $this->renderer = $renderer;
    parent::__construct(
      '.',
      $module_handler,
      $theme_handler
    );
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Twig\Error\LoaderError
   *   Thrown if a template matching $name cannot be found.
   */
  protected function findTemplate($name, $throw = TRUE) {
    [$id, $variant] = $this->parseIdAndVariant($name);
    try {
      $component = $this->componentDiscovery->find($id);
      // Validate if the variant is valid.
      if ($variant && !in_array($variant, $component->getVariants())) {
        $message = sprintf('Invalid variant "%s" for component "%s".', $variant, $id);
        throw new LoaderError($message);
      }
      $template = $component->getTemplateName($variant ?? '');
      $path = sprintf(
        '%s%s%s',
        $component->getMetadata()->getPath(),
        DIRECTORY_SEPARATOR,
        $template
      );
    }
    catch (ComponentNotFoundException | TemplateNotFoundException  $e) {
      throw new LoaderError($e->getMessage(), $e->getCode(), $e);
    }
    if ($path || !$throw) {
      return $path;
    }

    throw new LoaderError(sprintf('Unable to find template "%s" in the components registry.', $name));
  }

  /**
   * {@inheritdoc}
   */
  public function exists($name): bool {
    // Check the filesystem for the template.
    try {
      if (!parent::exists($name)) {
        return FALSE;
      }
    }
    catch (LoaderError $e) {
      return FALSE;
    }

    try {
      [$id, $variant] = $this->parseIdAndVariant($name);
      $component = $this->componentDiscovery->find($id);
      return !$variant || in_array($variant, $component->getVariants());
    }
    catch (ComponentNotFoundException | LoaderError $e) {
      return FALSE;
    }
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\cl_components\Exception\ComponentNotFoundException
   * @throws \Exception
   */
  public function getSourceContext($name): Source {
    [$component_id, $variant] = $this->parseIdAndVariant($name);
    $component = $this->componentDiscovery->find($component_id);
    $source = parent::getSourceContext($name);
    $lib_build = ['#attached' => ['library' => [$component->getLibraryName()]]];
    $this->renderer->render($lib_build);
    $code = "{{ cl_components_additional_context('" . addcslashes($component_id, "'") . "', '" . addcslashes($variant ?: '', "'") . "', " . (int) ($this->debug && $this->additionalDebug) . ") }}"
      . PHP_EOL . $source->getCode();
    if ($this->debug) {
      $code = "{# start cl_component $name #}" . PHP_EOL
        . "<!-- start cl_component $name -->" . PHP_EOL
        . $code . PHP_EOL
        . "<!-- end component $name -->" . PHP_EOL
        . "{# end cl_component $name #}" . PHP_EOL;
    }
    if ($this->additionalDebug) {
      $debug_lib_build = ['#attached' => ['library' => ['cl_components/cl_debug']]];
      $this->renderer->render($debug_lib_build);
    }
    return new Source($code, $source->getName(), $source->getPath());
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheKey($name): string {
    return 'cl-component::' . parent::getCacheKey($name);
  }

  /**
   * Parse ID and variant from the template key.
   *
   * @param string $name
   *   The template name as provided in the include/embed.
   *
   * @return array
   *   The component ID and variant.
   *
   * @throws \Twig\Error\LoaderError
   */
  public function parseIdAndVariant(string $name): array {
    if (isset($this->idVariantCache[$name])) {
      return $this->idVariantCache[$name];
    }
    $path = parent::findTemplate($name);
    // Check if there is a metadata.json in the same directory.
    $dir = dirname($path);
    $metadata_path = sprintf(
      '%s%smetadata.json',
      rtrim($dir, '/'),
      DIRECTORY_SEPARATOR
    );
    $error_message = sprintf('Unable to find a valid metadata.json for the component with template: "%s"', $name);
    if (!file_exists($metadata_path)) {
      throw new LoaderError($error_message);
    }
    $metadata_contents = file_get_contents($metadata_path);
    $metadata_parsed = Json::decode($metadata_contents ?: '{}');
    $id = $metadata_parsed['machineName'] ?? '';
    if (empty($id)) {
      throw new LoaderError($error_message);
    }
    // If requested, parse the variant.
    $variant = preg_replace(
    // Turn @name/my/path/to/component-id--variant.twig => variant.
      [
        '@\.twig$@',
        '@^.*--@',
      ],
      '',
      $name
    );
    $variant = preg_match('@.*/[^/]*--.*\.twig@', $name) ? $variant : '';
    $available_variants = $metadata_parsed['variants'] ?? [];
    if (empty($variant) || in_array($variant, $available_variants) === FALSE) {
      $variant = '';
    }
    $this->idVariantCache[$name] = [$id, $variant];
    return [$id, $variant];
  }

}
