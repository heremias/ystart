<?php

namespace Drupal\config_partial_export\Utility;

use Drupal\Core\Config\ConfigManagerInterface;
use Drupal\Core\Config\StorageComparer;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\ModuleInstallerInterface;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drush\Commands\DrushCommands;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;


/**
 * Provides functionality to be used by CLI tools.
 */
class CommandHelper extends DrushCommands {

  /**
   * @var ConfigManagerInterface
   */
  protected $configManager;

  protected $configStorage;

  protected $configStorageSync;

  protected $eventDispatcher;

  protected $lock;

  protected $configTyped;

  protected $moduleInstaller;

  protected $themeHandler;

  protected $stringTranslation;

  /**
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;


  /**
   * @param ConfigManagerInterface $configManager
   * @param StorageInterface $configStorage
   * @param StorageInterface $configStorageSync
   */
  public function __construct(ConfigManagerInterface $configManager, StorageInterface $configStorage, StorageInterface $configStorageSync, ModuleHandlerInterface $moduleHandler, EventDispatcherInterface $eventDispatcher, LockBackendInterface $lock, TypedConfigManagerInterface $configTyped, ModuleInstallerInterface $moduleInstaller, ThemeHandlerInterface $themeHandler, TranslationInterface $stringTranslation)
  {
    parent::__construct();
    $this->configManager = $configManager;
    $this->configStorage = $configStorage;
    $this->configStorageSync = $configStorageSync;
    $this->moduleHandler = $moduleHandler;
    $this->eventDispatcher = $eventDispatcher;
    $this->lock = $lock;
    $this->configTyped = $configTyped;
    $this->moduleInstaller = $moduleInstaller;
    $this->themeHandler = $themeHandler;
    $this->stringTranslation = $stringTranslation;
  }

  /**
   * Writes a YAML configuration file to the specified directory.
   *
   * @param string $key
   *   Configuration key.
   * @param \Drupal\Core\Config\StorageInterface $source_storage
   *   The source storage.
   * @param \Drupal\Core\Config\StorageInterface $destination_storage
   *   The source storage.
   *
   * @return bool
   *   Whether or not the configuration was moved from source to destination.
   */
  function _config_partial_export_write_config($key, StorageInterface $source_storage, StorageInterface $destination_storage, $destination_dir) {
    $data = $source_storage->read($key);
    // New config.
    if (empty($data)) {
      $data = $this->configManager->getConfigFactory()->get($key)->getRawData();
    }
    $destination_storage->write($key, $data);

    $this->logger()->info(dt('Writing !name to !target.', [
      '!name' => $key,
      '!target' => $destination_dir,
    ]));

    return $data;
  }

  /**
   * Checking if a configuration matches a wildcard.
   *
   * @param string $input
   *   The string that contains the wildcard.
   * @param \Drupal\Core\Config\StorageInterface $storage
   *   The source storage.
   *
   * @return array
   *   The list of keys.
   */
  function _config_partial_export_get_wildcard_keys($input, StorageInterface $storage) {
    // Get the strings around the wildcard.
    $split = explode('*', $input);
    $matching_keys = [];

    // Load the possible keys that start with the first prefix.
    $possible_keys = $storage->listAll($split[0]);

    // Check each key if they match the strings.
    foreach ($possible_keys as $config_key) {
      $match = TRUE;
      $counter = strlen($split[0]);

      for ($i = 1; $i < count($split); $i++) {
        if (!empty($split[$i])) {
          // Check if the partial exists after the last check.
          $pos = strpos($config_key, $split[$i], $counter);
          // If no "match" was found for this partial, it should fail.
          if ($pos === FALSE) {
            $match = FALSE;
          }
          // Increment the counter by the position found and length of the match.
          $counter += ($pos + strlen($split[$i]));
        }
      }
      if ($match) {
        $matching_keys[] = $config_key;
      }
    }
    return $matching_keys;
  }

  /**
   * Gets the list of changed configurations.
   *
   * @return array|bool
   *   TRUE if there are no changes.
   */
  function _config_partial_export_get_changes() {
    $storage_comparer = new StorageComparer($this->configStorageSync, $this->configStorage, $this->configManager);

    $source_list = $this->configStorageSync->listAll();
    $change_list = $storage_comparer->createChangelist();
    if (empty($source_list) || !$change_list->hasChanges()) {
      $this->output()->writeln(dt('There are no configuration changes.'));
      return TRUE;
    }
    $diff = $change_list->getChangelist();
    if (!empty($diff)) {
      foreach ($diff as $action => $config_names) {
        if (empty($config_names)) {
          unset($diff[$action]);
          continue;
        }
        sort($diff[$action]);
      }
    }

    return $diff;
  }
}
