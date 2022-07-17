<?php

namespace Drupal\static_export_stream_wrapper_git\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configuration form for Git File System stream wrapper.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'static_export_stream_wrapper_git_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['static_export_stream_wrapper_git.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('static_export_stream_wrapper_git.settings');

    $form['git_binary'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Path to the git binary'),
      '#required' => TRUE,
      '#description' => $this->t('This module relies on the git command line binary for all its functionality.'),
      '#default_value' => $config->get('git_binary'),
    ];

    $form['repo_dir'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Relative path to the directory where the git repository is located'),
      '#required' => TRUE,
      '#description' => $this->t('It must start with a leading slash. It should be writable by the user running your web-server (usually <em>www-data</em> or similar). Relative to <em>DRUPAL_ROOT</em> (@drupal_root). It can be set outside <em>DRUPAL_ROOT</em> using "../". It must be already cloned and configured to be able to run usual git commands on it (git add, git commit, git push, etc)', ['@drupal_root' => DRUPAL_ROOT]),
      '#default_value' => $config->get('repo_dir'),
    ];

    $form['data_dir'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Data directory path'),
      '#required' => TRUE,
      '#description' => $this->t('Path inside the git repository where data should be stored'),
      '#default_value' => $config->get('data_dir'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $gitBinary = $form_state->getValue('git_binary');
    if (!is_executable($gitBinary)) {
      $form_state->setErrorByName(
        'git_binary',
        $this->t('Path to the git binary is not executable.'));
    }

    $repoDir = $form_state->getValue('repo_dir');

    if (strpos($repoDir, '/') !== 0) {
      $form_state->setErrorByName(
        'repo_dir',
        $this->t('Repository directory path must start with a leading slash.'));
    }

    if (!is_dir($repoDir)) {
      $form_state->setErrorByName(
        'repo_dir',
        $this->t('Repository directory is not a directory.'));
    }

    if (!is_writable($repoDir)) {
      $form_state->setErrorByName(
        'repo_dir',
        $this->t('Repository directory is not writable.'));
    }

    if (!is_writable($repoDir . '/.git/config')) {
      $form_state->setErrorByName(
        'repo_dir',
        $this->t('Repository directory is not properly configured as a git directory. You should run "git init" on it.'));
    }

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('static_export_stream_wrapper_git.settings');
    $config
      ->set('git_binary', $form_state->getValue('git_binary'))
      ->set('repo_dir', rtrim($form_state->getValue('repo_dir'), '/'))
      ->set('data_dir', '/' . trim($form_state->getValue('data_dir'), '/'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
