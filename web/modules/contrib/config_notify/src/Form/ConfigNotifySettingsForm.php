<?php

namespace Drupal\config_notify\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Core\Link;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\config_notify\NotifierService;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Datetime\DateFormatterInterface;

/**
 * Settings form for config_notify.
 */
class ConfigNotifySettingsForm extends ConfigFormBase {

  /**
   * The notifier service.
   *
   * @var \Drupal\config_notify\NotifierService
   */
  protected $notifier;

  /**
   * Drupal\Core\Messenger\MessengerInterface definition.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Drupal\Core\Extension\ModuleHandlerInterface definition.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Drupal\Core\Datetime\DateFormatterInterface definition.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * Constructs the object.
   *
   * @param \Drupal\config_notify\NotifierService $notifier
   *   The notifier service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   */
  public function __construct(NotifierService $notifier, MessengerInterface $messenger, ModuleHandlerInterface $module_handler, DateFormatterInterface $date_formatter) {
    $this->notifier = $notifier;
    $this->messenger = $messenger;
    $this->moduleHandler = $module_handler;
    $this->dateFormatter = $date_formatter;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config_notify.notifier'),
      $container->get('messenger'),
      $container->get('module_handler'),
      $container->get('date.formatter')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'config_notify.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'config_notify_settings_form';
  }

  /**
   * Build the form.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('config_notify.settings');

    $changes = $this->notifier->checkChanges();
    if ($changes) {
      $message = $this->t('There are configuration changes.');
      $this->messenger->addMessage($message, 'warning');
    }
    else {
      $message = $this->t('There are no configuration changes.');
      $this->messenger->addMessage($message, 'status');
    }

    $form['notification_frequency'] = [
      '#markup' => '<hr /><h3>' . $this->t('Notifications frequency') . '</h3>',
    ];

    $form['cron'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Notify on cron'),
      '#description' => $this->t('If there are changes in configuration, notify of these via cron.'),
      '#default_value' => $config->get('cron'),
    ];

    $form['daily'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Once a day only (cron)'),
      '#description' => $this->t('On cron, only send one notification per day.'),
      '#default_value' => $config->get('daily'),
    ];

    $last_sent = $this->notifier->getLastNotificationSent();
    if ($last_sent) {
      $date = $this->dateFormatter->format($last_sent);
      $form['message'] = [
        '#markup' => '<p>' . '<strong>' . $this->t('Last notification was sent:') . '</strong> ' . $date . '</p>',
      ];
    }

    $form['notification_settings'] = [
      '#markup' => '<hr /><h3>' . $this->t('Notification settings') . '</h3>',
    ];

    $form['list_changes'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('List changes'),
      '#description' => $this->t("Sends through a list of config's that have changes."),
      '#default_value' => $config->get('list_changes'),
    ];

    $form['list_changes_limit'] = [
      '#type' => 'number',
      '#title' => $this->t('Limit changes list'),
      '#description' => $this->t('Limit the number of config files that can be sent through.') .
      '<br/>' . $this->t('If set to 0 it will be unlimited.') .
      '<br/><strong>' . $this->t('Please note that setting to unlimited can cause styling issues in some places if large config changes are detected.') . '</strong>',
      '#default_value' => $config->get('list_changes_limit') ? $config->get('list_changes_limit') : 0,
      '#states' => [
        'visible' => [
          ':checkbox[name="list_changes"]' => [
            'checked' => TRUE,
          ],
        ],
        'required' => [
          ':checkbox[name="list_changes"]' => [
            'checked' => TRUE,
          ],
        ],
      ],
    ];

    $form['add_host'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Send host information'),
      '#description' => $this->t("Adds host information into notifications."),
      '#default_value' => $config->get('add_host'),
    ];

    $host = \Drupal::request()->getSchemeAndHttpHost();
    $form['custom_host_value'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Custom host value'),
      '#description' => $this->t('Add a custom value to be sent with notifications. Leave empty for %current (current host)', ['%current' => $host]),
      '#default_value' => $config->get('custom_host_value'),
      '#attributes' => [
        'placeholder' => $host,
      ],
      '#states' => [
        'visible' => [
          ':checkbox[name="add_host"]' => [
            'checked' => TRUE,
          ],
        ],
      ],
    ];

    $form['notification_types'] = [
      '#markup' => '<hr /><h3>' . $this->t('Notification types') . '</h3>',
    ];

    $email = $this->config('system.site')->get('mail');
    $form['email'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Email notification'),
      '#description' => $this->t('Send the notification via email to the site administrator or the address specified below.'),
      '#default_value' => $config->get('email'),
    ];
    $form['email_to'] = [
      '#type' => 'email',
      '#title' => $this->t('Send email to this address'),
      '#description' => $this->t('Send the email to the specified address. Leave empty for %email (site administrator)', ['%email' => $email]),
      '#default_value' => $config->get('email_to'),
      '#attributes' => [
        'placeholder' => $email,
      ],
      '#states' => [
        'visible' => [
          ':checkbox[name="email"]' => [
            'checked' => TRUE,
          ],
        ],
      ],
    ];

    if ($this->moduleHandler->moduleExists('slack')) {
      $is_slack_configured = \Drupal::config('slack.settings')->get('slack_webhook_url') ?: NULL;
      $form['slack'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Slack notification'),
        '#description' => $this->t('Send the notification via slack.'),
        '#default_value' => $config->get('slack'),
        '#attributes' => [
          'disabled' => !$is_slack_configured,
        ],
      ];

      if (!$is_slack_configured) {
        $url = Url::fromRoute('slack.admin_settings');
        $form['slack_message'] = [
          '#markup' => '<p>' . $this->t('NOTICE: While Slack module is enabled, it is not properly configured. Please <a href="@configure-slack">configure Slack</a>.', ['@configure-slack' => $url->toString()]) . '</p>',
        ];
      }
    }
    else {
      $options = [
        'attributes' => [
          'target' => '_blank',
        ],
      ];
      $url = Url::fromUri('https://www.drupal.org/project/slack');
      $url->setOptions($options);
      $link = Link::fromTextAndUrl($this->t('Slack module'), $url)->toString();
      $form['slack_message'] = [
        '#markup' => '<p>' . $this->t('Install and configure the slack module to send notifications via slack.') . $link . '</p>',
      ];
      $form['slack'] = [
        '#type' => 'hidden',
        '#default_value' => FALSE,
      ];
    }

    $form['actions']['#type'] = 'actions';
    if ($changes) {
      $form['actions']['notify_now'] = [
        '#type' => 'submit',
        '#value' => $this->t('Notify now'),
        '#submit' => [[$this, 'notifyNow']],
      ];
    }

    $form['#theme'] = 'system_config_form';
    return parent::buildForm($form, $form_state);
  }

  /**
   * Submit handler.
   */
  public function notifyNow(array &$form, FormStateInterface $form_state) {
    $this->saveValues($form, $form_state);

    $changes = $this->notifier->checkChanges();
    if (!$changes) {
      return;
    }

    $config = $this->config('config_notify.settings');
    if ($config->get('slack')) {
      $message = $this->notifier->getDefaultMessage(TRUE);
      if ($this->notifier->notifySlack($message)) {
        $this->messenger->addMessage($this->t('Slack: Message was successfully sent.'));
      }
      else {
        $this->messenger->addMessage($this->t('Slack: Message not sent. Please check log messages for details'), 'warning');
      }
    }

    if ($config->get('email')) {
      $message = $this->notifier->getDefaultMessage();
      if ($this->notifier->notifyEmail($message, $config->get('email_to'))) {
        $this->messenger->addMessage($this->t('Email: Message was successfully sent.'));
      }
      else {
        $this->messenger->addMessage($this->t('Email: Message not sent. Please check log messages for details'), 'warning');
      }
    }

    $this->notifier->setLastNotification(strtotime('now'));
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    $this->saveValues($form, $form_state);
  }

  /**
   * Save form submitted values.
   */
  public function saveValues(array &$form, FormStateInterface $form_state) {
    $cron = $form_state->getValue('cron');
    $daily = $form_state->getValue('daily');
    $email = $form_state->getValue('email');
    $email_to = $form_state->getValue('email_to');
    $slack = $form_state->getValue('slack');
    $list_changes = $form_state->getValue('list_changes');
    $list_changes_limit = $form_state->getValue('list_changes_limit');
    $add_host = $form_state->getValue('add_host');
    $custom_host_value = $form_state->getValue('custom_host_value');

    $this->config('config_notify.settings')
      ->set('cron', $cron)
      ->set('daily', $daily)
      ->set('slack', $slack)
      ->set('email', $email)
      ->set('email_to', $email_to)
      ->set('list_changes', $list_changes)
      ->set('list_changes_limit', $list_changes_limit)
      ->set('add_host', $add_host)
      ->set('custom_host_value', $custom_host_value)
      ->save();
  }

}
