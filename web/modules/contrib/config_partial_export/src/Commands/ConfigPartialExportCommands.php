<?php

namespace Drupal\config_partial_export\Commands;

use Drupal\Core\Config\FileStorage;
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
use Drush\Drush;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Defines Drush commands for the Search API.
 */
class ConfigPartialExportCommands extends DrushCommands {

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
   * Command description here.
   *
   * @param $config
   *   Configuration keys, comma separated.
   * @param $label
   *   A config destination label (i.e. a key in $config_directories array in settings.php). Defaults to 'sync'.
   *
   * @param array $options
   *   An associative array of options whose values come from cli, aliases, config, etc.
   * @option changelist
   *   Shows the list of changed active config.
   * @option show-destinations
   *   Choose from a list of config destinations.   *
   *
   * @usage drush config-partial-export webform.webform.*
   *   Export all webform config.
   * @usage drush cpex webform.webform.*
   *   Export all webform config.
   *
   * @command config-partial-export
   * @aliases cpex
   */
  public function configPartialExport($config = '', $label = '', $options = ['changelist' => '', 'show-destinations' => ['description']]) {
    $changelist = $this->getConfig()->get('changelist', 0);
    if ($changelist) {
      $changes = $this->_config_partial_export_get_changes();
      if (!empty($changes)) {
        $this->output()->writeln(dt("Your configuration has changed:"));
        foreach ($changes as $key => $values) {
          $this->output()->writeln($key);
          foreach ($values as $value) {
            $this->output()->writeln('- ' . $value);
          }
        }
      }
      return TRUE;
    }
    global $config_directories;
    $choices = drush_map_assoc(array_keys($config_directories));
    unset($choices[CONFIG_ACTIVE_DIRECTORY]);
    // Throw a warning if someone wants to show destinations but supplied one.
    if (!empty($label) and $this->getConfig()->get('show-destinations')) {
      $this->logger()->error('Error, supplied both a destination and the list. Using the supplied destination and ignoring the list');
    }
    // List out the destinations to select.
    elseif ($this->getConfig()->get('show-destinations')) {
      $label = $this->_choice($choices, 'Choose a destination.');
      if (empty($label)) {
        return $this->_user_abort();
      }
    }

    // Check to see if destination is still undefined, set it to default.
    if (empty($label)) {
      $label = CONFIG_SYNC_DIRECTORY;
    }

    // Check if label doesn't exist.
    if (!in_array($label, $choices)) {
      $msg = dt('Error !target not found as a configuration target.',
        ['!target' => $label]);
      return drush_set_error('NO_CONFIG_DEST', $msg);
    }

    $destination_dir = config_get_config_directory($label);
    $destination_storage = new FileStorage($destination_dir);

    $config_keys = explode(",", $config);
    foreach ($config_keys as $config_key) {
      // Look for a wildcard character.
      if (strpos($config_key, '*') !== FALSE) {
        $wildcard_keys = $this->_config_partial_export_get_wildcard_keys($config_key, $this->configStorage);
        foreach ($wildcard_keys as $wildcard_key) {
          $this->_config_partial_export_write_config($wildcard_key, $this->configStorage, $destination_storage, $destination_dir);
        }
      }
      else {
        $this->_config_partial_export_write_config($config_key, $this->configStorage, $destination_storage, $destination_dir);
      }
    }

    return TRUE;
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

  /**
   * @param null $msg
   * @return bool
   */
  function _user_abort($msg = NULL) {
    drush_set_context('DRUSH_USER_ABORT', TRUE);
    $this->logger()->info($msg ? $msg : dt('Aborting.'));
    return FALSE;
  }

  /**
   * @param $options
   * @param string $prompt
   * @param string $label
   * @param array $widths
   * @return bool
   */
  function _choice($options, $prompt = 'Enter a number.', $label = '!value', $widths = []) {
    $this->output()->writeln(dt($prompt));

    // Preflight so that all rows will be padded out to the same number of columns
    $array_pad = 0;
    foreach ($options as $key => $option) {
      if (is_array($option) && (count($option) > $array_pad)) {
        $array_pad = count($option);
      }
    }

    $rows[] = array_pad(['[0]', ':', 'Cancel'], $array_pad + 2, '');
    $selection_number = 0;
    foreach ($options as $key => $option) {
      if ((substr($key, 0, 3) == '-- ') && (substr($key, -3) == ' --')) {
        $rows[] = array_pad(['', '', $option], $array_pad + 2, '');
      }
      else {
        $selection_number++;
        $row = ["[$selection_number]", ':'];
        if (is_array($option)) {
          $row = array_merge($row, $option);
        }
        else {
          $row[] = dt($label, ['!number' => $selection_number, '!key' => $key, '!value' => $option]);
        }
        $rows[] = $row;
        $selection_list[$selection_number] = $key;
      }
    }
    drush_print_table($rows, FALSE, $widths);
    drush_print_pipe(array_keys($options));

    // If the user specified --choice, then make an
    // automatic selection.  Cancel if the choice is
    // not an available option.
    if (($choice = $this->getConfig()->get('choice', FALSE)) !== FALSE) {
      // First check to see if $choice is one of the symbolic options
      if (array_key_exists($choice, $options)) {
        return $choice;
      }
      // Next handle numeric selections
      elseif (array_key_exists($choice, $selection_list)) {
        return $selection_list[$choice];
      }
      return FALSE;
    }

    // If the user specified --no, then cancel; also avoid
    // getting hung up waiting for user input in --pipe and
    // backend modes.  If none of these apply, then wait,
    // for user input and return the selected result.
    if (!drush_get_context('DRUSH_NEGATIVE') && !drush_get_context('DRUSH_AFFIRMATIVE') && !drush_get_context('DRUSH_PIPE')) {
      while ($line = trim(fgets(STDIN))) {
        if (array_key_exists($line, $selection_list)) {
          return $selection_list[$line];
        }
      }
    }
    // We will allow --yes to confirm input if there is only
    // one choice; otherwise, --yes will cancel to avoid ambiguity
    if (drush_get_context('DRUSH_AFFIRMATIVE') && (count($options) == 1)) {
      return $selection_list[1];
    }
    return FALSE;
  }

  /**
   * @param $rows
   * @param bool $header
   * @param array $widths
   * @param null $handle
   * @return mixed
   */
  function _print_table($rows, $header = FALSE, $widths = [], $handle = NULL) {
    $tbl = $this->_drush_format_table($rows, $header, $widths);
    $output = $tbl->getTable();
    if (!stristr(PHP_OS, 'WIN')) {
      $output = str_replace("\r\n", PHP_EOL, $output);
    }

    drush_print(rtrim($output), 0, $handle);
    return $tbl;
  }

  function _drush_format_table($rows, $header = FALSE, $widths = [], $console_table_options = []) {
    // Add defaults.
    $tbl = new \ReflectionClass('Console_Table');
    $console_table_options += [CONSOLE_TABLE_ALIGN_LEFT, ''];
    $tbl = $tbl->newInstanceArgs($console_table_options);

    $auto_widths = drush_table_column_autowidth($rows, $widths);

    // Do wordwrap on all cells.
    $newrows = [];
    foreach ($rows as $rowkey => $row) {
      foreach ($row as $col_num => $cell) {
        $newrows[$rowkey][$col_num] = wordwrap($cell, $auto_widths[$col_num], "\n", TRUE);
        if (isset($widths[$col_num])) {
          $newrows[$rowkey][$col_num] = str_pad($newrows[$rowkey][$col_num], $widths[$col_num]);
        }
      }
    }
    if ($header) {
      $headers = array_shift($newrows);
      $tbl->setHeaders($headers);
    }

    $tbl->addData($newrows);
    return $tbl;
  }

}
