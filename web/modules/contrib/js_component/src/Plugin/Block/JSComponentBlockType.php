<?php

namespace Drupal\js_component\Plugin\Block;

use Drupal\Core\Utility\Token;
use Drupal\Component\Utility\Html;
use Drupal\Core\Template\Attribute;
use Drupal\js_component\Event\Events;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Annotation\Translation;
use Drupal\js_component\Plugin\JSComponent;
use Drupal\Core\Annotation\ContextDefinition;
use Drupal\js_component\JSComponentAttachment;
use Drupal\Core\Asset\LibraryDiscoveryInterface;
use Drupal\Core\Block\Annotation\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\js_component\Event\BuildComponentDataEvent;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\Element\FormElementInterface;
use Drupal\Core\Render\ElementInfoManagerInterface;
use Drupal\js_component\JSComponentManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Define JS component block.
 *
 * @Block(
 *   id = "js_component",
 *   category = @Translation("JS Component"),
 *   admin_label = @Translation("JS Component"),
 *   context_definitions = {
 *     "node" = @ContextDefinition("entity:node", label = @Translation("Node"), required = FALSE)
 *   },
 *   deriver = "\Drupal\js_component\Plugin\Deriver\JSComponentsBlocksDeriver"
 * )
 */
class JSComponentBlockType extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * @var \Drupal\Core\Utility\Token
   */
  protected $token;

  /**
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * @var string
   */
  protected $componentRootId;

  /**
   * @var \Drupal\js_component\Plugin\JSComponent
   */
  protected $componentInstance;

  /**
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * @var \Drupal\Core\Asset\LibraryDiscoveryInterface
   */
  protected $libraryDiscovery;

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * @var \Drupal\Core\Render\ElementInfoManagerInterface
   */
  protected $elementInfoManager;

  /**
   * @var \Drupal\js_component\JSComponentManager
   */
  protected $jsComponentManager;

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
        'overrides' => [],
        'js_component' => [],
        'component_data' => [],
      ] + parent::defaultConfiguration();
  }

  /**
   * JS component block constructor.
   *
   * @param array $configuration
   *   The plugin configurations.
   * @param $plugin_id
   *   The plugin identifier.
   * @param $plugin_definition
   *   The plugin metadata definition.
   * @param \Drupal\Core\Utility\Token $token
   *   The Drupal token utility service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   * @param \Drupal\Core\Asset\LibraryDiscoveryInterface $library_discovery
   *   The library discovery service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param ElementInfoManagerInterface $element_info_manager
   *   The element information manager service.
   * @param \Drupal\js_component\JSComponentManagerInterface $js_component_manager
   *   The JS component manager service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    Token $token,
    ModuleHandlerInterface $module_handler,
    EventDispatcherInterface $event_dispatcher,
    LibraryDiscoveryInterface $library_discovery,
    EntityTypeManagerInterface  $entity_type_manager,
    ElementInfoManagerInterface $element_info_manager,
    JSComponentManagerInterface $js_component_manager
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->token = $token;
    $this->moduleHandler = $module_handler;
    $this->eventDispatcher = $event_dispatcher;
    $this->libraryDiscovery = $library_discovery;
    $this->entityTypeManager = $entity_type_manager;
    $this->elementInfoManager = $element_info_manager;
    $this->jsComponentManager = $js_component_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('token'),
      $container->get('module_handler'),
      $container->get('event_dispatcher'),
      $container->get('library.discovery'),
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.element_info'),
      $container->get('plugin.manager.js_component')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form['#process'][] = [$this, 'processBuildComponent'];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockValidate($form, FormStateInterface $form_state) {
    parent::blockValidate($form, $form_state);

    $component = $this->getComponentInstance();

    /** @var \Drupal\js_component\JSComponentFormInterface $handler */
    if ($handler = $component->settingsClassHandler($this->getComponentSettings())) {
      $subform = $form['js_component'];
      $handler->validateComponentForm(
        $subform,
        SubformState::createForSubform(
          $subform, $form, $form_state
        )
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    parent::blockSubmit($form, $form_state);

    $this->configuration['js_component'] = $this->extractComponentValues(
      $form, $form_state
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = ['#block' => $this];
    $component = $this->getComponentInstance();

    try {
      if ($component->hasTemplate()) {
        $build += $this->buildComponentTwigTemplate();
      } else {
        $build += $this->buildComponentInlineTemplate();
      }
    } catch (\Exception $exception) {
      watchdog_exception('js_component', $exception);
    }

    return $build;
  }

  /**
   * Get block components classes.
   *
   * @return array
   *   An array of the block component classes.
   */
  public function getBlockComponentClasses() {
    $classes[] = 'js-component';
    $classes[] = 'js-component--' . Html::getClass($this->getComponentPluginId());

    return $classes;
  }

  /**
   * The build component process callback.
   *
   * @param array $form
   *   An array of the form elements.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state instance.
   *
   * @return array
   *   An array of the processed form elements.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function processBuildComponent(array $form, FormStateInterface $form_state) {
    $component = $this->getComponentInstance();
    $component_parents = array_merge($form['#parents'], ['js_component']);

    $form['js_component'] = [
      '#type' => 'details',
      '#title' => $this->t('JS Component'),
      '#open' => TRUE,
      '#tree' => TRUE,
      '#parents' => $component_parents
    ];

    if ($component->settingsAllowToken()
      && $this->moduleHandler->moduleExists('token')) {
      $form['js_component']['token'] = [
        '#theme' => 'token_tree_link',
        '#token_types' => ['node'],
        '#weight' => 100,
      ];
    }

    /** @var \Drupal\js_component\JSComponentFormInterface $handler */
    if ($handler = $component->settingsClassHandler($this->getComponentSettings())) {
      $subform = ['#parents' => $component_parents];

      $form['js_component'] += $handler->buildComponentForm(
        $subform,
        SubformState::createForSubform(
          $subform,
          $form_state->getCompleteForm(),
          $form_state
        )
      );
    }
    else if ($component->hasSettings()) {
      $form['js_component'] += $this->buildComponentFormElements();
    }

    $plugin_id = str_replace('-', '_', $this->getComponentPluginId());
    $plugin_config = $this->getConfiguration()['js_component'] ?? [];

    $alter_types = [
      'js_component_form',
      "js_component_{$plugin_id}_form",
    ];

    $this->moduleHandler->alter(
      $alter_types, $form['js_component'], $plugin_config, $form_state
    );

    $children = $this->filterElementChildren($form['js_component'], ['token']);

    if (count($children) === 0) {
      unset($form['js_component']);
    }

    return $form;
  }

  /**
   * Resolve the component subform array.
   *
   * @param array $form
   *   The form elements to assess.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state instance.
   *
   * @return array
   *   The component subform array.
   */
  protected function resolveComponentSubform(
    array $form,
    FormStateInterface $form_state
  ) {
    $parent_keys = [
      ['js_component'],
      ['settings', 'js_component']
    ];

    foreach ($parent_keys as $parents) {
      $key_exist = NULL;
      $subform = NestedArray::getValue($form, $parents, $key_exist);

      if (!$key_exist || !is_array($subform)) {
        continue;
      }

      return $subform;
    }

    return [];
  }

  /**
   * Extract component setting values.
   *
   * @param array $form
   *   An array of the form elements.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state instance.
   *
   * @return array
   *   An array of the component values.
   */
  protected function extractComponentValues(
    array $form,
    FormStateInterface $form_state
  ) {
    $values = [];

    try {
      $subform = $this->resolveComponentSubform($form, $form_state);

      if (empty($subform)) {
        throw new \RuntimeException(
          'Unable to resolve the component subform.'
        );
      }
      $component = $this->getComponentInstance();

      $parent_form = NestedArray::getValue(
        $form_state->getCompleteForm(),
        array_slice($subform['#array_parents'], 0, -1)
      );

      $subform_state = SubformState::createForSubform(
        $subform, $parent_form, $form_state
      );

      if ($component->hasSettings()) {
        $values = $form_state->getValue('js_component');
      }
      elseif ($handler = $component->settingsClassHandler($this->getComponentSettings())) {
        $handler->submitComponentForm($subform, $subform_state);
        $values = $handler->getConfiguration();
      }

      $this->moduleHandler->invokeAll('js_component_form_submit', [
        &$values,
        $subform_state
      ]);
    } catch (\Exception $exception) {
      watchdog_exception('js_component', $exception);
    }

    return $values;
  }

  /**
   * Build the JS component twig template.
   *
   * @return array
   *   Am render array of the component template.
   *
   * @throws \Exception
   */
  protected function buildComponentTwigTemplate() {
    $build = [
      '#theme' => $this->getComponentId(),
      '#settings' => $this->getComponentProcessedSettings(),
    ];
    $attachment = $this->getComponentTemplateAttachment();

    if ($attached = $attachment->getIterator()->getArrayCopy()) {
      $build['#attached'] = $attached;
    }

    return $build;
  }

  /**
   * Build JS component template.
   *
   * @return array
   *   Am render array of the component template.
   *
   * @throws \Exception
   */
  protected function buildComponentInlineTemplate() {
    $build = [];

    $attributes = new Attribute();
    $attributes['id'] = $this->getComponentRootId();
    $attributes['class'] = $this->getBlockComponentClasses();

    $attachment = $this->getComponentTemplateAttachment();

    switch ($this->getComponentInstance()->settingsScope()) {
      case JSComponent::SETTINGS_SCOPE_DOM:
        $attachment->addDrupalSettings(
          'jsComponent',
          $this->getComponentSettingsAttachment()
        );
        break;
      case JSComponent::SETTINGS_SCOPE_ATTRIBUTE:
        foreach ($this->getComponentProcessedSettings() as $key => $value) {
          $attributes["data-{$key}"] = $value;
        }
        break;
    }

    $build += [
      '#type' => 'inline_template',
      '#template' => '<div {{ attributes }}></div>',
      '#context' => [
        'attributes' => $attributes,
      ],
    ];

    if ($attached = $attachment->getIterator()->getArrayCopy()) {
      $build['#attached'] = $attached;
    }

    return $build;
  }

  /**
   * JS component identifier.
   *
   * @return mixed
   */
  protected function getComponentId() {
    return $this->pluginDefinition['component_id'];
  }

  /**
   * Get JS component root identifier.
   *
   * @return string
   *   The component root identifier.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  protected function getComponentRootId() {
    if (!isset($this->componentRootId)) {
      $prefix = 'settings:';
      $root_id = $this->getComponentInstance()->rootId();

      if (strpos($root_id, $prefix) !== FALSE) {
        $name = substr($root_id, strlen($prefix));
        $settings = $this->getComponentSettings();

        if (isset($settings[$name])) {
          $root_id = $settings[$name];
        }
      }

      $this->componentRootId = Html::getUniqueId(
        $root_id
      );
    }

    return $this->componentRootId;
  }

  /**
   * Get JS component instance.
   *
   * @return \Drupal\js_component\Plugin\JSComponent
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  protected function getComponentInstance() {
    if (!isset($this->componentInstance)) {
      $this->componentInstance = $this->jsComponentManager
        ->createInstance($this->getComponentPluginId(), [
          'overrides' => $this->getConfigurationOverrides()
        ]);
    }

    return $this->componentInstance;
  }

  /**
   * Get JS component template attachment.
   *
   * @return \Drupal\js_component\JSComponentAttachment
   *   The JS component attachment instance.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  protected function getComponentTemplateAttachment() {
    $attachment = new JSComponentAttachment();

    if ($settings = $this->getComponentDataAttachment()) {
      $attachment->addDrupalSettings('jsComponent', $settings);
    }

    if ($this->hasLibraryForComponent()) {
      $attachment->addLibrary("js_component/{$this->getComponentId()}");
    }

    return $attachment;
  }

  /**
   * Get JS component data attachment array.
   *
   * @return array
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  protected function getComponentDataAttachment() {
    $attachment = [];

    $root_id = $this->getComponentRootId();
    $plugin_id = $this->getComponentPluginId();

    if ($data = $this->buildComponentData()) {
      $attachment[$plugin_id][$root_id]['data'] = $data;
    }

    return $attachment;
  }

  /**
   * Get JS component setting attachment.
   *
   * @return array
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  protected function getComponentSettingsAttachment() {
    $attachment = [];

    $root_id = $this->getComponentRootId();
    $plugin_id = $this->getComponentPluginId();

    if ($settings = $this->getComponentProcessedSettings()) {
      $attachment[$plugin_id][$root_id]['settings'] = $settings;
    }

    return $attachment;
  }

  /**
   * JS component plugin identifier.
   *
   * @return string
   *   The JS component plugin identifier.
   */
  protected function getComponentPluginId() {
    $plugin_id = $this->getPluginId();
    return substr($plugin_id, strpos($plugin_id, ':') + 1);
  }

  /**
   * Build the component data.
   *
   * @return array
   *   An array of the component data.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  protected function buildComponentData() {
    $component_data_event = new BuildComponentDataEvent(
      $this->getConfiguration(),
      $this->getComponentInstance(),
      $this->getComponentSettings()
    );

    return ($this->eventDispatcher->dispatch(
      Events::BUILD_COMPONENT_DATA,
      $component_data_event
    ))->build();
  }

  /**
   * Get the component data provider instance.
   *
   * @return bool|\Drupal\js_component\JsComponentDataProviderInterface
   *   Return the component data provider; otherwise FALSE if it doesn't exist.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  protected function getComponentDataProvider() {
    $component = $this->getComponentInstance();

    return $component->dataProviderClassHandler(
      $this->getComponentSettings()
    );
  }

  /**
   * JS component has libraries defined.
   *
   * @return bool
   *   Determine if the JS component has a library defined.
   */
  protected function hasLibraryForComponent() {
    $status = $this
      ->libraryDiscovery
      ->getLibraryByName('js_component', "{$this->getComponentId()}");

    return $status !== FALSE;
  }

  /**
   * Recursive clean values.
   *
   * @param array $values
   *   An array of values.
   *
   * @return array
   *   An array of cleaned values.
   */
  protected function recursiveCleanValues(array $values) {
    foreach ($values as $key => &$value) {
      if (is_array($value)) {
        $value = $this->recursiveCleanValues($value);
      }
    }

    return array_filter($values);
  }

  /**
   * Build the component form elements.
   *
   * @return array
   *   The component form elements.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  protected function buildComponentFormElements() {
    $elements = [];

    $component = $this->getComponentInstance();
    $settings = $this->getComponentSettings();

    foreach ($component->settings() as $field_name => $field_info) {
      if (!isset($field_info['type'])
        || !$this->elementIsValid($field_info['type'])) {
        continue;
      }
      $element = $this->formatFormElement($field_info);

      if (isset($settings[$field_name])
        && !empty($settings[$field_name])) {
        $element['#default_value'] = $settings[$field_name];
      }

      $elements[$field_name] = $element;
    }

    return $elements;
  }

  /**
   * Format form element.
   *
   * @param array $element_info
   *   An array of the element key and value.
   *
   * @return array
   *   The formatted form element.
   */
  protected function formatFormElement(array $element_info) {
    $element = [];

    foreach ($element_info as $key => $value) {
      if (empty($value)) {
        continue;
      }
      $element["#{$key}"] = $value;
    }

    return $element;
  }

  /**
   * Form element is valid.
   *
   * @param $type
   *   The type of form element.
   *
   * @return bool
   *   Return TRUE if the element type is valid; otherwise FALSE.
   */
  protected function elementIsValid($type) {
    if (!$this->elementInfoManager->hasDefinition($type)) {
      return FALSE;
    }
    $element_type = $this
      ->elementInfoManager
      ->createInstance($type);

    return $element_type instanceof FormElementInterface;
  }

  /**
   * Get configuration overrides.
   *
   * @return array
   */
  protected function getConfigurationOverrides() {
    return $this->getConfiguration()['overrides'];
  }

  /**
   * Get the component data.
   *
   * @return array
   *   An array of component data object.
   */
  protected function getComponentData() {
    return $this->getConfiguration()['component_data'];
  }

  /**
   * Get the JS component settings.
   *
   * @return array
   *   An array of component settings.
   */
  protected function getComponentSettings() {
    return $this->getConfiguration()['js_component'] ?? [];
  }

  /**
   * Get the JS component processed settings.
   *
   * @return array
   *   An array of processed settings.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  protected function getComponentProcessedSettings() {
    $settings = $this->getComponentSettings();

    if ($this->getComponentInstance()->settingsAllowToken()) {
      $this->processTokenReplacements($settings);
    }

    return $this->recursiveCleanValues($settings);
  }

  /**
   * Process token replacements on values.
   *
   * @param array $values
   *   An array of values that could contain tokens.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  protected function processTokenReplacements(array &$values) {
    $data = [];

    if ($node = $this->getContextValue('node')) {
      $data['node'] = $node;
    }

    array_walk($values, function (&$value) use ($data) {
      $value = $this->token->replace($value, $data, ['clear' => TRUE]);
    });
  }

  /**
   * Filter element children.
   *
   * @param array $elements
   *   An array of elements.
   * @param array $exclude_keys
   *   An array of excluded keys.
   *
   * @return array
   *   An array of elements filtered.
   */
  protected function filterElementChildren(array $elements, array $exclude_keys) {
    return array_filter(Element::children($elements), function ($name) use ($exclude_keys) {
      return !in_array($name, $exclude_keys);
    });
  }
}
