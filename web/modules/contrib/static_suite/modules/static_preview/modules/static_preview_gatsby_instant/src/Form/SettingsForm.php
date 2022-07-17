<?php

namespace Drupal\static_preview_gatsby_instant\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\static_build\Plugin\StaticBuilderPluginInterface;
use Drupal\static_build\Plugin\StaticBuilderPluginManagerInterface;
use Drupal\static_suite\Release\ReleaseManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configuration form for Static Preview - Gatsby - Instant.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * Release manager.
   *
   * @var \Drupal\static_suite\Release\ReleaseManagerInterface
   */
  protected $releaseManager;

  /**
   * Static builder manager.
   *
   * @var \Drupal\static_build\Plugin\StaticBuilderPluginManagerInterface
   */
  protected $staticBuilderPluginManager;

  /**
   * Constructs a \Drupal\system\ConfigFormBase object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\static_build\Plugin\StaticBuilderPluginManagerInterface $staticBuilderPluginManager
   *   Static builder manager.
   * @param \Drupal\static_suite\Release\ReleaseManagerInterface $releaseManager
   *   Release manager.
   */
  public function __construct(ConfigFactoryInterface $config_factory, StaticBuilderPluginManagerInterface $staticBuilderPluginManager, ReleaseManagerInterface $releaseManager) {
    parent::__construct($config_factory);
    $this->staticBuilderPluginManager = $staticBuilderPluginManager;
    $this->releaseManager = $releaseManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('plugin.manager.static_builder'),
      $container->get('static_suite.release_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'static_preview_gatsby_instant_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['static_preview_gatsby_instant.settings'];
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\static_suite\StaticSuiteException
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('static_preview_gatsby_instant.settings');

    // Get live and preview paths. Since each plugin instance internally uses
    // the same release manager, create an instance of each plugin and get its
    // path before creating a new instance.
    $previewBuilder = $this->staticBuilderPluginManager->createInstance(
      'gatsby',
      ['run-mode' => StaticBuilderPluginInterface::RUN_MODE_PREVIEW]
    );
    $runModeOptions[StaticBuilderPluginInterface::RUN_MODE_PREVIEW] = StaticBuilderPluginInterface::RUN_MODE_PREVIEW . ' (' . $previewBuilder->getReleaseManager()
      ->getCurrentPointerPath() . ')';
    $previewDir = NULL;
    if ($config->get('run_mode') === StaticBuilderPluginInterface::RUN_MODE_PREVIEW) {
      $this->releaseManager = $previewBuilder->getReleaseManager();
      $previewDir = $this->releaseManager->getCurrentPointerPath();
    }

    $liveBuilder = $this->staticBuilderPluginManager->createInstance(
      'gatsby',
      ['run-mode' => StaticBuilderPluginInterface::RUN_MODE_LIVE]
    );
    $runModeOptions[StaticBuilderPluginInterface::RUN_MODE_LIVE] = StaticBuilderPluginInterface::RUN_MODE_LIVE . ' (' . $liveBuilder->getReleaseManager()
      ->getCurrentPointerPath() . ')';
    if ($config->get('run_mode') === StaticBuilderPluginInterface::RUN_MODE_LIVE) {
      $this->releaseManager = $liveBuilder->getReleaseManager();
      $previewDir = $this->releaseManager->getCurrentPointerPath();
    }

    $form['run_mode'] = [
      '#type' => 'select',
      '#title' => $this->t('Gatsby public folder'),
      '#required' => TRUE,
      '#description' => $this->t("Select the folder that contains the preview component (usually 'preview')."),
      '#options' => $runModeOptions,
      '#default_value' => $config->get('run_mode'),
    ];

    $form['preview_component_path'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Preview component path'),
      '#required' => TRUE,
      '#description' => $this->t("It must start with a leading slash. Path to your Gatsby preview component, e.g.- /preview, inside Gatsby's public folder (currently '%previewDir'). Please refer to the docs for guidance on what should that component do.", ['%previewDir' => $previewDir]),
      '#default_value' => $config->get('preview_component_path'),
    ];

    $form['preview_component_example'] = [
      '#type' => 'details',
      '#title' => $this->t('Preview component example'),
      '#description' => $this->t("Example of a preview component, located at src/Preview.jsx, that generates a page at /preview path, inside Gatsby's public folder."),
      '#open' => TRUE,
      '#markup' =>
      "<pre>

import React from 'react';
import Article from 'Article';
import Homepage from 'Homepage';
import Page from 'Page';

const Preview = ({ pageContext }) => {
    // This switch depends on the structure of your data.
    switch (pageContext.node.data.content.bundle) {
        case 'article':
            return &lt;Article pageContext={pageContext} /&gt;;
        case 'homepage':
            return &lt;Homepage pageContext={pageContext} /&gt;;
        case 'page':
            return &lt;Page pageContext={pageContext} /&gt;;
        default:
            return null;
    }
};

export default Preview;
</pre>",
    ];

    $form['build_trigger_regexp_list'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Regular Expressions to detect changed files that trigger a "@preview" rebuild', ['@preview' => StaticBuilderPluginInterface::RUN_MODE_PREVIEW]),
      '#required' => FALSE,
      '#description' => $this->t(
        "If a global part of your site is built with content coming from Drupal (e.g.- a global header or footer) it won't be updated unless a build is run. You should define, inside <a href='@static_build_config_form_link'>Static Build module's configuration form</a>, which files must trigger a '@preview' build.",
        [
          '@static_build_config_form_link' => Url::fromRoute('static_build.settings')
            ->toString(),
          '@preview' => StaticBuilderPluginInterface::RUN_MODE_PREVIEW,
        ]),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $previewPath = $form_state->getValue('preview_component_path');
    if (strpos($previewPath, '/') !== 0) {
      $form_state->setErrorByName(
        'preview_component_path',
        $this->t('Preview component path must start with a leading slash.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('static_preview_gatsby_instant.settings');
    $config
      ->set('run_mode', rtrim($form_state->getValue('run_mode'), '/'))
      ->set('preview_component_path', rtrim($form_state->getValue('preview_component_path'), '/'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
