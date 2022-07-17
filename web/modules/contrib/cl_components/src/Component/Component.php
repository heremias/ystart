<?php

namespace Drupal\cl_components\Component;

use Drupal\cl_components\Exception\InvalidComponentException;
use Drupal\cl_components\Exception\TemplateNotFoundException;
use Drupal\Component\Utility\Html;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Template\Attribute;

/**
 * Simple value object that contains information about the component.
 */
class Component {

  use StringTranslationTrait;

  private const TEMPLATE_VARIANT_SEPARATOR = '--';

  /**
   * The styles to include in the component.
   *
   * @var string[]
   */
  private array $styles;

  /**
   * The javascript objects to include on the component.
   *
   * @var string[]
   */
  private array $scripts;

  /**
   * The component's metadata.
   *
   * This includes the available variants, and documentation.
   *
   * @var \Drupal\cl_components\Component\ComponentMetadata
   */
  private ComponentMetadata $metadata;

  /**
   * The component ID.
   *
   * @var string
   */
  private string $id;

  /**
   * The Twig templates in the repository.
   *
   * @var string[]
   */
  private array $templates;

  /**
   * Is debug mode on?
   *
   * @var bool
   */
  private bool $debugMode;

  /**
   * Component constructor.
   *
   * @param string $id
   *   The component ID.
   * @param array $templates
   *   The templates.
   * @param string[] $styles
   *   The styles.
   * @param string[] $scripts
   *   The JS.
   * @param \Drupal\cl_components\Component\ComponentMetadata $metadata
   *   The component metadata.
   *
   * @throws \Drupal\cl_components\Exception\InvalidComponentException
   *   If the component is invalid.
   */
  public function __construct(string $id, array $templates, array $styles, array $scripts, ComponentMetadata $metadata, bool $debug_mode) {
    $this->styles = $styles;
    $this->templates = $templates;
    $this->scripts = $scripts;
    $this->id = $id;
    $this->metadata = $metadata;
    $this->debugMode = $debug_mode;
    $this->validate();
  }

  /**
   * Validates the data for the component object.
   *
   * @throws \Drupal\cl_components\Exception\InvalidComponentException
   *   If the component is invalid.
   */
  private function validate() {
    $num_main_templates = count($this->getMainTemplates());
    if ($num_main_templates === 0) {
      $message = sprintf('Unable to find main template %s.twig or any of its variants.', $this->getId());
      throw new InvalidComponentException($message);
    }
    if (strpos($this->getId(), '/') !== FALSE) {
      $message = sprintf('Component ID cannot contain slashes: %s', $this->getId());
      throw new InvalidComponentException($message);
    }
  }

  /**
   * The main templates for the component.
   *
   * @return string[]
   *   The template names.
   */
  public function getMainTemplates(): array {
    return array_filter($this->getTemplates(), function (string $template) {
      $regexp = sprintf('%s(%s[^\.]+)?\.(twig)', $this->getId(), static::TEMPLATE_VARIANT_SEPARATOR);
      return (bool) preg_match('/' . $regexp . '/', $template);
    });
  }

  /**
   * The template names.
   *
   * @return string[]
   *   The names.
   */
  public function getTemplates(): array {
    return $this->templates;
  }

  /**
   * The ID.
   *
   * @return string
   *   The ID.
   */
  public function getId(): string {
    return $this->id;
  }

  /**
   * The styles.
   *
   * @return string[]
   *   The stylesheet paths.
   */
  public function getStyles(): array {
    return $this->styles;
  }

  /**
   * The JS.
   *
   * @return string[]
   *   The script paths.
   */
  public function getScripts(): array {
    return $this->scripts;
  }

  /**
   * Get the template name for the selected variant.
   *
   * @param string $variant
   *   The template variant.
   *
   * @return string
   *   The name of the template.
   *
   * @throws \Drupal\cl_components\Exception\TemplateNotFoundException
   */
  public function getTemplateName(string $variant = ''): string {
    $filename = sprintf('%s%s%s.twig', $this->getId(), static::TEMPLATE_VARIANT_SEPARATOR, $variant);
    // If it cannot find the variant, fall back to the base.
    if (!in_array($filename, $this->getMainTemplates())) {
      $filename = sprintf('%s.twig', $this->getId());
    }
    if (!in_array($filename, $this->getMainTemplates())) {
      $message = sprintf('Unable to find template %s.', $filename);
      throw new TemplateNotFoundException($message);
    }
    return $filename;
  }

  /**
   * The auto-computed library name.
   *
   * @return string
   *   The library name.
   */
  public function getLibraryName(): string {
    return sprintf('cl_components/%s', $this->getId());
  }

  /**
   * The variants.
   *
   * @return array
   *   The available variants.
   */
  public function getVariants(): array {
    return $this->metadata->getVariants();
  }

  /**
   * Gets the component metadata.
   *
   * @return \Drupal\cl_components\Component\ComponentMetadata
   *   The component metadata.
   */
  public function getMetadata(): ComponentMetadata {
    return $this->metadata;
  }

  /**
   * Calculates additional context for this template.
   *
   * @param string $variant
   *   The variant.
   *
   * @return array
   *   The additional context to inject to component templates.
   */
  public function additionalRenderContext($variant = ''): array {
    $metadata = $this->getMetadata();
    $status = $metadata->getStatus();
    $classes = array_map([Html::class, 'cleanCssIdentifier'], [
      'cl-component',
      'cl-component--' . $this->getId(),
      'cl-component--' . $metadata->getComponentType(),
      'cl-component--' . $status,
    ]);
    $classes = array_map('strtolower', $classes);
    $attributes = [
      'class' => $classes,
      'data-cl-component-id' => $this->getId(),
      'data-cl-component-variant' => $variant,
    ];
    // If debug mode is enabled, then add a class.
    if ($this->debugMode) {
      $attributes['class'][] = 'cl-component--debug';
      $args = [
        '%id' => $this->getId(),
        '%name' => $metadata->getName(),
        '%variant' => $variant ?: '- none -',
        '%status' => $status,
        '%description' => $metadata->getDescription(),
      ];
      $title = $this->t("[DEBUG]\n\nComponent: \"%name\" (%id).\nVariant: %variant\nStatus: %status\nDescription: %description", $args);
      $attributes['title'] = $attributes['title'] ?? $title;
    }
    return [
      'clAttributes' => new Attribute($attributes),
      'clMeta' => $metadata->normalize(),
      'variant' => $variant,
    ];
  }

}
