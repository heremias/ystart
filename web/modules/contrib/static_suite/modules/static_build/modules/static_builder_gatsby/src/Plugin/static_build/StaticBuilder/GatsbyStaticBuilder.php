<?php

namespace Drupal\static_builder_gatsby\Plugin\static_build\StaticBuilder;

use Drupal\static_build\Plugin\StaticBuilderPluginBase;
use Drupal\static_suite\StaticSuiteException;

/**
 * Provides a static builder for Gatsby.
 *
 * @StaticBuilder(
 *  id = "gatsby",
 *  label = @Translation("Gatsby"),
 *  description = @Translation("Static builder to generate sites using Gatsby"),
 *  host = "local"
 * )
 */
class GatsbyStaticBuilder extends StaticBuilderPluginBase {

  protected const GATSBY_CACHE_DIRNAME = '.cache';

  protected const GATSBY_PUBLIC_DIRNAME = 'public';

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\static_suite\StaticSuiteException
   */
  public function preBuild(): void {
    $this->deleteTempDirs();
  }

  /**
   * {@inheritdoc}
   */
  public function build(): void {
    $config = $this->configFactory->get('static_builder_gatsby.settings');

    $nodeOptions = $config->get('node.' . $this->configuration['run-mode'] . '.options');
    $nodeCliOptions = $nodeOptions ? escapeshellarg($nodeOptions) : NULL;
    $gatsbyOptions = $config->get('gatsby.' . $this->configuration['run-mode'] . '.options');
    $nodeGatsbyOptions = $gatsbyOptions ? escapeshellarg($gatsbyOptions) : NULL;
    $command = 'node ' . $nodeCliOptions . ' node_modules/.bin/gatsby build ' . $nodeGatsbyOptions . ' 2>&1';
    $cwd = $this->configuration['build-dir'] . $config->get('base_dir');
    // @see https://www.gatsbyjs.org/docs/environment-variables/
    // This code sets "GATSBY_IS_PREVIEW" or "GATSBY_IS_LIVE" accordingly.
    $envName = 'GATSBY_IS_' . strtoupper($this->configuration['run-mode']);
    $env = [$envName => 'true'];

    if (!is_dir($cwd) || !is_readable($cwd)) {
      throw new StaticSuiteException("Unable to build " . $this->configuration['run-mode'] . ". Base directory for Gatsby (" . $cwd . ") not present or not readable.");
    }

    $lines = [];
    $cliCommand = $this->cliCommandFactory->create($command, $cwd, $env);
    $cliCommand->open();
    while ($line = $cliCommand->readStdOut()) {
      $this->logMessage('[GATSBY] ' . trim($line));
      $lines[] = $line;
    }
    $exitCode = $cliCommand->close();

    // Delete previously set environment variable.
    putenv($envName);

    if ($exitCode !== 0) {
      throw new StaticSuiteException("Unable to build " . $this->configuration['run-mode'] . ". Exit code $exitCode. Trace: " . implode("\n", $lines));
    }
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\static_suite\StaticSuiteException
   */
  public function postBuild(): void {
    $config = $this->configFactory->get('static_builder_gatsby.settings');

    // Move or copy public dir contents to release dir.
    if ($this->configFactory->get('static_builder_gatsby.settings')
      ->get('delete.public')) {
      $this->release->moveToDir($this->configuration['build-dir'] . $config->get('base_dir') . "/" . self::GATSBY_PUBLIC_DIRNAME);
    }
    else {
      $this->release->copyToDir($this->configuration['build-dir'] . $config->get('base_dir') . "/" . self::GATSBY_PUBLIC_DIRNAME);
    }

    $this->deleteTempDirs();
  }

  /**
   * Deletes .cache and public dir.
   *
   * @throws \Drupal\static_suite\StaticSuiteException
   */
  protected function deleteTempDirs(): void {
    $config = $this->configFactory->get('static_builder_gatsby.settings');
    // Remove .cache and public dirs.
    if ($config->get('delete.cache')) {
      $this->deleteInsideBuildDir(($config->get('base_dir') ? $config->get('base_dir') . '/' : NULL) . self::GATSBY_CACHE_DIRNAME);
    }
    if ($config->get('delete.public')) {
      $this->deleteInsideBuildDir(($config->get('base_dir') ? $config->get('base_dir') . '/' : NULL) . self::GATSBY_PUBLIC_DIRNAME);
    }
  }

}
