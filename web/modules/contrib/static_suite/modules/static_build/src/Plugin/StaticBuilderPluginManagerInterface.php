<?php

namespace Drupal\static_build\Plugin;

use Drupal\Component\Plugin\Discovery\CachedDiscoveryInterface;
use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Cache\CacheableDependencyInterface;

/**
 * Typed interface for Static Builder Manager.
 */
interface StaticBuilderPluginManagerInterface extends PluginManagerInterface, CachedDiscoveryInterface, CacheableDependencyInterface {

  /**
   * {@inheritdoc}
   *
   * Wraps parent method to add typing.
   *
   * @return \Drupal\static_build\Plugin\StaticBuilderPluginInterface
   *   A newly created exporter object instance.
   *
   * @throws \Drupal\static_suite\StaticSuiteUserException
   */
  public function createInstance($plugin_id, array $configuration = []): StaticBuilderPluginInterface;

  /**
   * {@inheritdoc}
   *
   * Wraps parent method to add typing.
   *
   * @return \Drupal\static_build\Plugin\StaticBuilderPluginInterface
   *   A newly created exporter object instance, or a previously
   *   instantiated one if available.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   *   If the instance cannot be created, such as if the ID is invalid.
   * @throws \Drupal\static_suite\StaticSuiteUserException
   */
  public function getInstance(array $options): StaticBuilderPluginInterface;

  /**
   * Get local definitions.
   *
   * @return array
   *   Local definitions
   */
  public function getLocalDefinitions(): array;

  /**
   * Get cloud definitions.
   *
   * @return array
   *   Cloud definitions
   */
  public function getCloudDefinitions(): array;

  /**
   * Validates a builder's directory structure.
   *
   * Validates everything managed by Static Builder plugins (instances of
   * StaticBuilderPluginInterface), inside [base_dir]/[live|preview]. There is
   * a similar method inside ReleaseManagerInterface::validateDirStructure()
   * that checks the same for everything managed by ReleaseManagerInterface.
   *
   * @param \Drupal\static_build\Plugin\StaticBuilderPluginInterface $staticBuilderPlugin
   *   Static builder plugin.
   *
   * @return array
   *   Array of translated errors if any.
   */
  public function validateBuilderDirStructure(StaticBuilderPluginInterface $staticBuilderPlugin): array;

  /**
   * Validates that all directory structures relevant to a builder are correct.
   *
   * Validates directories/files managed by:
   * 1) Static Builder plugins (instances of StaticBuilderPluginInterface), and
   * 2) Release manager (instances of ReleaseManagerInterface)
   *
   * @param string $plugin_id
   *   The ID of the plugin being validated.
   * @param array $configuration
   *   An array of configuration relevant to the plugin instance.
   *
   * @return array
   *   Array of translated errors if any.
   */
  public function validateAllDirsStructure(string $plugin_id, array $configuration = []): array;

}
