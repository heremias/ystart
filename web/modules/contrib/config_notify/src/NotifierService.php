<?php

namespace Drupal\config_notify;

use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Config\ConfigManagerInterface;
use Drupal\Core\Config\ImportStorageTransformer;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Config\StorageComparer;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Notifier service.
 */
class NotifierService {
  use StringTranslationTrait;

  /**
   * Drupal\Core\Messenger\MessengerInterface definition.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Drupal\Core\Config\ConfigManagerInterface definition.
   *
   * @var \Drupal\Core\Config\ConfigManagerInterface
   */
  protected $configManager;

  /**
   * Drupal\Core\Config\ImportStorageTransformer definition.
   *
   * @var \Drupal\Core\Config\ImportStorageTransformer
   */
  protected $configImportTransformer;

  /**
   * Drupal\Core\Config\StorageInterface definition.
   *
   * @var \Drupal\Core\Config\StorageInterface
   */
  protected $configStorageSync;

  /**
   * Drupal\Core\Config\StorageInterface definition.
   *
   * @var \Drupal\Core\Config\StorageInterface
   */
  protected $configStorageActive;

  /**
   * Drupal\Core\Datetime\DateFormatterInterface definition.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * Drupal\Core\Extension\ModuleHandlerInterface definition.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Drupal\Core\State\StateInterface definition.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * Drupal\Core\Mail\MailManagerInterface definition.
   *
   * @var \Drupal\Core\Mail\MailManagerInterface
   */
  protected $pluginManagerMail;

  /**
   * Drupal\Core\Config\ConfigFactoryInterface definition.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Drupal\Core\Language\LanguageManagerInterface definition.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * Constructs a new NotifierService object.
   */
  public function __construct(MessengerInterface $messenger, ConfigManagerInterface $config_manager, ImportStorageTransformer $config_import_transformer, StorageInterface $config_storage_sync, StorageInterface $config_storage_active, DateFormatterInterface $date_formatter, ModuleHandlerInterface $module_handler, StateInterface $state, MailManagerInterface $plugin_manager_mail, ConfigFactoryInterface $config_factory, LanguageManagerInterface $language_manager) {
    $this->messenger = $messenger;
    $this->configManager = $config_manager;
    $this->configImportTransformer = $config_import_transformer;
    $this->configStorageSync = $config_storage_sync;
    $this->configStorageActive = $config_storage_active;
    $this->dateFormatter = $date_formatter;
    $this->moduleHandler = $module_handler;
    $this->state = $state;
    $this->pluginManagerMail = $plugin_manager_mail;
    $this->configFactory = $config_factory;
    $this->languageManager = $language_manager;
  }

  /**
   * Check for changes in active configuration.
   *
   * @return bool
   *   Whether there are changes on the active configuration or not.
   */
  public function checkChanges() {
    $syncStorage = $this->configImportTransformer->transform($this->configStorageSync);
    $source_list = $syncStorage->listAll();
    $storage_comparer = new StorageComparer($syncStorage, $this->configStorageActive);

    $changes = !(empty($source_list) || !$storage_comparer->createChangelist()->hasChanges());
    return $changes;
  }

  /**
   * Returns a file lists containing configuration changes.
   *
   * @return string
   *   Line spaced separated file names
   */
  public function getChanges() {
    $list_changes = $this->configFactory->get('config_notify.settings')->get('list_changes');
    $list_changes_limit = $this->configFactory->get('config_notify.settings')->get('list_changes_limit');

    if (!$this->checkChanges() || !$list_changes) {
      return "";
    }

    $syncStorage = $this->configImportTransformer->transform($this->configStorageSync);
    $storage_comparer = new StorageComparer($syncStorage, $this->configStorageActive);
    $storage_comparer->createChangelist();

    $change_list = [];
    foreach ($storage_comparer->getAllCollectionNames() as $collection) {
      foreach ($storage_comparer->getChangelist(NULL, $collection) as $config_names) {
        if (!empty($config_names)) {
          foreach ($config_names as $config_name) {
            $change_list[] = trim($config_name);
          }
        }
      }
    }

    if ($list_changes_limit > 0) {
      $count = count($change_list);
      if ($count > $list_changes_limit) {
        $diff = $count - $list_changes_limit;

        array_splice($change_list, $count - $diff, $diff);

        $singular = "and @count more change.";
        $plural = "and @count more changes.";

        $change_list[] = \Drupal::translation()->formatPlural($diff, $singular, $plural);
      }
    }

    return implode(PHP_EOL, $change_list);
  }

  /**
   * Returns the message when there are configuration changes.
   *
   * @param bool $markdown
   *   Returned message contains markdown or not.
   *
   * @return string
   *   The default message
   */
  public function getDefaultMessage($markdown = FALSE) {
    $bold = ($markdown) ? '*' : '';
    $wrapping = ($markdown) ? '```' : '';
    $wrapping_single = ($markdown) ? '`' : '';
    $message = "";

    $changes = $this->getChanges();
    $list_changes = $this->configFactory->get('config_notify.settings')->get('list_changes');
    $host = $this->getHostInformation();

    if ($host) {
      $message .= $wrapping_single . $host . "$wrapping_single\n\n";
    }

    $message .= $this->t("There are configuration changes not exported on the site.");

    if ($list_changes && $changes !== "") {
      $message .= "\n\n$bold" . $this->t("Config changes:") . "$bold\n\n";
      $message .= $wrapping . $changes . $wrapping;
    }

    return $message;
  }

  /**
   * Returns when the last notification was sent.
   *
   * @return mixed
   *   Timestamp or false.
   */
  public function getLastNotificationSent() {
    return $this->state->get('config_notify.last_sent');
  }

  /**
   * Sets the last notification time.
   *
   * @param int $time
   *   Time to set.
   */
  public function setLastNotification($time) {
    $this->state->set('config_notify.last_sent', $time);
  }

  /**
   * Returns host information.
   *
   * @return mixed
   *   String or false.
   */
  private function getHostInformation() {
    $add_host = $this->configFactory->get('config_notify.settings')->get('add_host');
    $custom_host_value = trim((string) $this->configFactory->get('config_notify.settings')->get('custom_host_value'));
    if ($add_host) {
      $current_host = \Drupal::request()->getSchemeAndHttpHost();
      return $custom_host_value !== "" ? $custom_host_value : $current_host;
    }
    return FALSE;
  }

  /**
   * Sends slack notification.
   *
   * @param string $message
   *   Message to send to slack.
   *
   * @return bool
   *   Whether the message was sent or not.
   */
  public function notifySlack($message) {
    if ($this->moduleHandler->moduleExists('slack')) {
      $response = \Drupal::service('slack.slack_service')->sendMessage($message);
      return ($response && RedirectResponse::HTTP_OK == $response->getStatusCode());
    }

    return FALSE;
  }

  /**
   * Sends email notification.
   *
   * @param string $message
   *   Message to send via email.
   * @param string $email
   *   Recipient of email if different from site administrator.
   *
   * @return bool
   *   Whether the message was sent or not.
   */
  public function notifyEmail($message, $email = NULL) {
    $module = 'config_notify';
    $key = 'config_notify';
    $to = empty($email) ? $this->configFactory->get('system.site')->get('mail') : $email;
    $params['message'] = $message;
    $langcode = $this->languageManager->getCurrentLanguage()->getId();
    $send = TRUE;

    $result = $this->pluginManagerMail->mail($module, $key, $to, $langcode, $params, NULL, $send);
    return (bool) $result['result'];
  }

}
