<?php

namespace Drupal\sfc\Commands;

use Consolidation\SiteAlias\SiteAliasManagerAwareTrait;
use Drupal\Component\Plugin\Discovery\CachedDiscoveryInterface;
use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\sfc\ComponentFilenameInterface;
use Drush\Commands\DrushCommands;
use Drush\SiteAlias\SiteAliasManagerAwareInterface;

/**
 * Drush command file for SFC commands.
 */
class ComponentCommands extends DrushCommands implements SiteAliasManagerAwareInterface {

  use SiteAliasManagerAwareTrait;

  /**
   * The plugin manager.
   *
   * @var \Drupal\Component\Plugin\PluginManagerInterface
   */
  protected $manager;

  /**
   * The watch file path.
   *
   * @var string
   */
  protected $watchFilePath;

  /**
   * ComponentCommands constructor.
   *
   * @param \Drupal\Component\Plugin\PluginManagerInterface $manager
   *   The plugin manager.
   * @param string $watch_file_path
   *   The watch file path.
   */
  public function __construct(PluginManagerInterface $manager, $watch_file_path = 'public://sfc_watch_file.txt') {
    $this->manager = $manager;
    $this->watchFilePath = $watch_file_path;
  }

  /**
   * Writes the assets and/or source for a given component.
   *
   * @param string $id
   *   The plugin ID.
   *
   * @command sfc:write
   */
  public function write($id) {
    /** @var \Drupal\sfc\ComponentInterface $component */
    $component = $this->manager->createInstance($id);
    $component->writeAssets();
  }

  /**
   * Watches for changes in all components.
   *
   * This is a good alternative to disabling the "data" cache bin for normal
   * components.
   *
   * @param array $options
   *   Options for this command.
   *
   * @command sfc:watch
   * @option run-once If the command should only be run once.
   */
  public function watch(array $options = ['run-once' => FALSE]) {
    $definitions = $this->manager->getDefinitions();
    if (empty($definitions)) {
      $this->io()->warning('No components found.');
      return 0;
    }
    $this->io()->writeln('Watching for changes...');
    $component_mtimes = [];
    foreach (array_keys($definitions) as $id) {
      /** @var \Drupal\sfc\ComponentInterface $component */
      $component = $this->manager->createInstance($id);
      if ($component instanceof ComponentFilenameInterface) {
        $component_mtimes[$id] = filemtime($component->getComponentFileName());
      }
    }
    while (TRUE) {
      clearstatcache();
      $clear_cache = FALSE;
      $clear_definitions = FALSE;
      foreach (array_keys($definitions) as $id) {
        /** @var \Drupal\sfc\ComponentInterface $component */
        $component = $this->manager->createInstance($id);
        if ($component->shouldWriteAssets()) {
          $this->io()->writeln("Writing assets for $id");
          $this->processManager()->drush($this->siteAliasManager()->getSelf(), 'sfc:write', [$id])->mustRun();
          $clear_cache = TRUE;
        }
        if ($component instanceof ComponentFilenameInterface && filemtime($component->getComponentFileName()) > $component_mtimes[$id]) {
          $this->io()->writeln("Clearing cache for $id");
          $clear_definitions = TRUE;
          $clear_cache = TRUE;
          $component_mtimes[$id] = filemtime($component->getComponentFileName());
        }
      }
      if ($clear_cache) {
        $this->processManager()->drush($this->siteAliasManager()->getSelf(), 'cc', ['render'])->mustRun();
        file_put_contents($this->watchFilePath, time());
      }
      if ($clear_definitions && $this->manager instanceof CachedDiscoveryInterface) {
        $this->manager->clearCachedDefinitions();
      }
      usleep(250000);
      if ($options['run-once']) {
        break;
      }
    }
    return 1;
  }

}
