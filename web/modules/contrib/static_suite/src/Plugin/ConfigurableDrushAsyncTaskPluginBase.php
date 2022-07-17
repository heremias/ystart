<?php

namespace Drupal\static_suite\Plugin;

use Drupal\Component\Utility\NestedArray;

/**
 * Base class for plugins that execute a configurable asynchronous drush task.
 *
 * Drush commands have special requirements when being forked, since WebMozart,
 * a Drupal dependency, throws an error if no HOME nor PATH is defined. This
 * base class ensures both environment variables are defined.
 */
abstract class ConfigurableDrushAsyncTaskPluginBase extends ConfigurableAsyncTaskPluginBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return NestedArray::mergeDeep(
      parent::defaultConfiguration(), [
        'env' => [
          'HOME' => getenv('HOME') ?: $this->discoverDefaultHome(),
          'PATH' => getenv('PATH') ?: '/usr/local/bin:/usr/bin:/bin',
        ],
      ]
    );
  }

  /**
   * Discover a default home for Drush commands.
   *
   * @return string
   *   Default home for Drush commands.
   */
  protected function discoverDefaultHome(): string {
    $processUser = posix_getpwuid(posix_geteuid());
    $defaultHome = '/home/nobody';
    if (!empty($processUser['dir'])) {
      $defaultHome = $processUser['dir'];
    }
    elseif (!empty($processUser['name'])) {
      $defaultHome = '/home/' . $processUser['name'];
    }
    return $defaultHome;
  }

}
