<?php

namespace Drupal\js_component\Plugin;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\TypedData\TypedDataInterface;
use Drupal\js_component\JsComponentDataProviderInterface;
use Drupal\js_component\JSComponentFormInterface;
use Drupal\js_component\JsComponentInjectContainerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Define JS component plugin.
 */
class JSComponent extends PluginBase implements JSComponentInterface, ContainerFactoryPluginInterface {

  /**
   * @var string
   */
  public const SETTINGS_SCOPE_DOM = 'dom';

  /**
   * @var string
   */
  public const SETTINGS_SCOPE_ATTRIBUTE = 'attribute';

  /**
   * @var ThemeHandlerInterface
   */
  protected $themeHandler;

  /**
   * @var ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * JS component constructor.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin identifier.
   * @param mixed $plugin_definition
   *   The plugin metadata definition.
   * @param \Drupal\Core\Extension\ThemeHandlerInterface $theme_handler
   *   The theme handler service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    ThemeHandlerInterface $theme_handler,
    ModuleHandlerInterface $module_handler
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->themeHandler = $theme_handler;
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition
  ) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('theme_handler'),
      $container->get('module_handler')
    );
  }

  /**
   * JS component label.
   *
   * @return string
   */
  public function label() {
    return $this->getProperty('label');
  }

  /**
   * JS component root identifier.
   *
   * @return string
   */
  public function rootId() {
    return $this->getProperty('root_id') ?: 'root';
  }

  /**
   * JS component settings.
   *
   * @return array
   */
  public function settings() {
    return $this->getProperty('settings') ?: [];
  }

  /**
   * JS component settings scope.
   *
   * @return mixed|string
   */
  public function settingsScope() {
    return $this->getProperty('settings_scope')
      ?: static::SETTINGS_SCOPE_DOM;
  }

  /**
   * JS component settings allow token.
   *
   * @return bool
   */
  public function settingsAllowToken() {
    return (bool) $this->getProperty('settings_allow_token')
      ?: FALSE;
  }

  /**
   * JS component has settings.
   *
   * @return bool
   *   Return TRUE if settings have been defined.
   */
  public function hasSettings() {
    $settings = $this->settings();
    return isset($settings) && !empty($settings);
  }

  /**
   * Js component class handlers.
   *
   * @return array
   *   An array of class handlers.
   */
  public function classHandlers() {
    return $this->getProperty('handlers') ?: [];
  }

  /**
   * Get the JS setting instance.
   *
   * @param array $configuration
   *   An array of component configurations.
   *
   * @return object|boolean
   *   The component form class instance.
   */
  public function settingsClassHandler(array $configuration = []) {
    $instance = $this->instantiateClassHandler(
      'component_form', $configuration
    );

    if ($instance == FALSE
      || !is_subclass_of($instance, JSComponentFormInterface::class)) {
      return FALSE;
    }

    return $instance;
  }

  /**
   * Get the JS data provider instance.
   *
   * @param array $configuration
   *   An array of component configurations.
   *
   * @return object|boolean
   *   The data provider class instance.
   */
  public function dataProviderClassHandler(array $configuration = []) {
    $instance = $this->instantiateClassHandler(
      'data_provider', $configuration
    );

    if ($instance === FALSE
      || !is_subclass_of($instance, JsComponentDataProviderInterface::class)) {
      return FALSE;
    }

    return $instance;
  }

  /**
   * JS component libraries.
   *
   * @return array
   */
  public function libraries() {
    return $this->getProperty('libraries');
  }

  /**
   * JS component has libraries.
   *
   * @return bool
   */
  public function hasLibraries() {
    return !empty($this->libraries());
  }

  /**
   * JS component template.
   *
   * @return string
   */
  public function template() {
    return $this->getProperty('template');
  }

  /**
   * JS component has template.
   *
   * @return bool
   */
  public function hasTemplate() {
    return !empty($this->template());
  }

  /**
   * JS component template path.
   *
   * @return string
   * @throws \Exception
   */
  public function getTemplatePath() {
    return "{$this->getProviderPath()}/{$this->templateFileInfo()['dirname']}";
  }

  /**
   * JS component template name, without extension.
   *
   * @return string
   */
  public function getTemplateName() {
    $file_info = $this->templateFileInfo();
    return basename($file_info['filename'], '.html');
  }

  /**
   * JS component provider.
   *
   * @return string
   */
  public function provider() {
    return $this->getProperty('provider');
  }

  /**
   * JS component identifier.
   *
   * @return string
   */
  public function componentId() {
    return $this->provider() . '.' . $this->getPluginId();
  }

  /**
   * Typed data validate.
   */
  public function validate() {
    return $this->typedData()->validate();
  }

  /**
   * Process JS component libraries.
   *
   * @return array
   * @throws \Exception
   */
  public function processLibraries() {
    $libraries = $this->libraries();
    $asset_path = $this->getProviderPath();

    if (isset($libraries['js'])) {
      foreach ($libraries['js'] as $js_path => $js_info) {
        if (isset($js_info['type']) && $js_info['type'] === 'external') {
          continue;
        }
        unset($libraries['js'][$js_path]);
        $libraries['js']["/{$asset_path}{$js_path}"] = $js_info;
      }
    }

    if (isset($libraries['css'])) {
      foreach ($libraries['css'] as $type => $files) {
        foreach ($files as $css_path => $css_info) {
          if (isset($css_info['type']) && $css_info['type'] === 'external') {
            continue;
          }
          unset($libraries['css'][$type][$css_path]);
          $libraries['css'][$type]["/{$asset_path}{$css_path}"] = $css_info;
        }
      }
    }

    return $libraries;
  }

  /**
   * Get JS component provider path.
   *
   * @return string
   * @throws \Exception
   */
  public function getProviderPath() {
    return drupal_get_path($this->getProviderType(), $this->provider());
  }

  /**
   * Get JS component template file info.
   *
   * @return array
   */
  protected function templateFileInfo() {
    return pathinfo($this->template());
  }

  /**
   * Get typed data property value.
   *
   * @param $name
   *   The name of the property.
   *
   * @return mixed
   */
  protected function getProperty($name) {
    $overrides = $this->propertyOverrides();

    return $overrides[$name]
      ?? $this->typedData()->get($name)->getValue();
  }

  /**
   * Allow properties to be overwritten at runtime.
   *
   * @return array
   */
  protected function propertyOverrides() {
    $overrides = $this->configuration['overrides'] ?? [];

    return array_intersect_key($overrides, array_flip([
      'root_id'
    ]));
  }

  /**
   * Typed data object.
   *
   * @return TypedDataInterface
   */
  protected function typedData() {
    return $this->configuration['typed_data'];
  }

  /**
   * Get Js component provider type.
   *
   * @return string
   * @throws \Exception
   */
  protected function getProviderType() {
    $provider = $this->provider();

    if ($this->themeHandler->themeExists($provider)) {
      return 'theme';
    }

    if ($this->moduleHandler->moduleExists($provider)) {
      return 'module';
    }

    throw new \Exception('JS component provider type is unknown.');
  }

  /**
   * Instantiate the class handler.
   *
   * @param $type
   *   The handler type.
   * @param array $configuration
   *   An array of configurations to pass along to the handler.
   *
   * @return bool|object
   *   The instantiated handler instance; otherwise FALSE if not found.
   */
  protected function instantiateClassHandler($type, array $configuration = []) {
    $handlers = $this->classHandlers();

    if (!isset($handlers[$type])) {
      return FALSE;
    }
    $classname = $handlers[$type];

    if (!class_exists($classname)) {
      throw new \RuntimeException(
        sprintf('The %s class does not exist!', $classname)
      );
    }

    if (!is_subclass_of($classname, JsComponentInjectContainerInterface::class)) {
      return new $classname($configuration);
    }

    return $classname::create($configuration, \Drupal::getContainer());
  }
}
