<?php

namespace Drupal\static_builder_codebuild\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Site\Settings;
use Drupal\Core\Url;

/**
 * Configuration form for Static Deployer S3.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'static_builder_codebuild_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['static_builder_codebuild.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('static_builder_codebuild.settings');

    // Most of this form is based on S3fs module.
    // @see https://www.drupal.org/project/s3fs
    $regionMap = [
      'us-east-1' => $this->t('US East - Northern Virginia (us-east-1)'),
      'us-east-2' => $this->t('US East - Ohio (us-east-2)'),
      'us-west-1' => $this->t('US West - Northern California  (us-west-1)'),
      'us-west-2' => $this->t('US West - Oregon (us-west-2)'),
      'us-gov-west-1' => $this->t('USA GovCloud Standard (us-gov-west-1)'),
      'eu-west-1' => $this->t('EU - Ireland  (eu-west-1)'),
      'eu-west-2' => $this->t('EU - London (eu-west-2)'),
      'eu-west-3' => $this->t('EU - Paris (eu-west-3)'),
      'eu-central-1' => $this->t('EU - Frankfurt (eu-central-1)'),
      'ap-south-1' => $this->t('Asia Pacific - Mumbai'),
      'ap-southeast-1' => $this->t('Asia Pacific - Singapore (ap-southeast-1)'),
      'ap-southeast-2' => $this->t('Asia Pacific - Sydney (ap-southeast-2)'),
      'ap-northeast-1' => $this->t('Asia Pacific - Tokyo (ap-northeast-1)'),
      'ap-northeast-2' => $this->t('Asia Pacific - Seoul (ap-northeast-2)'),
      'ap-northeast-3' => $this->t('Asia Pacific - Osaka-Local (ap-northeast-3)'),
      'sa-east-1' => $this->t('South America - Sao Paulo (sa-east-1)'),
      'cn-north-1' => $this->t('China - Beijing (cn-north-1)'),
      'cn-northwest-1' => $this->t('China - Ningxia (cn-northwest-1)'),
      'ca-central-1' => $this->t('Canada - Central (ca-central-1)'),
    ];

    $form['credentials'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Amazon Web Services Credentials'),
      '#description' => $this->t(
        "To set access and secret key you must use \$settings['static_builder_codebuild.access_key'] and \$settings['static_builder_codebuild.secret_key'] in your site's settings.php file.<br/>Submitting this form without adding your credentials will throw an error."
      ),
    ];

    $form['project'] = [
      '#type' => 'textfield',
      '#title' => $this->t('AWS Codebuild project name'),
      '#description' => $this->t('The name of the project that will be executed by this builder.'),
      '#required' => TRUE,
      '#default_value' => $config->get('project'),
    ];

    $form['region'] = [
      '#type' => 'select',
      '#options' => $regionMap,
      '#title' => $this->t('AWS Region'),
      '#description' => $this->t(
        'The region in which your bucket resides. Be careful to specify this accurately,
      as you are likely to see strange or broken behavior if the region is set wrong.<br>
      Use of the USA GovCloud region requires @SPECIAL_PERMISSION.<br>
      Use of the China - Beijing region requires a @CHINESE_AWS_ACCT.',
        [
          '@CHINESE_AWS_ACCT' => Link::fromTextAndUrl($this->t('亚马逊 AWS account'), Url::fromUri('http://www.amazonaws.cn'))
            ->toString(),
          '@SPECIAL_PERMISSION' => Link::fromTextAndUrl($this->t('special permission'), Url::fromUri('http://aws.amazon.com/govcloud-us/'))
            ->toString(),
        ]
      ),
      '#required' => TRUE,
      '#default_value' => $config->get('region'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $accessKey = Settings::get('static_builder_codebuild.access_key');
    $secretKey = Settings::get('static_builder_codebuild.secret_key');
    if (!$accessKey || !$secretKey) {
      $form_state->setErrorByName(
        'credentials',
        $this->t("No credentials found. You must define credentials in your site's settings.php file before submitting this form")
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('static_builder_codebuild.settings');
    $config
      ->set('project', $form_state->getValue('project'))
      ->set('region', $form_state->getValue('region'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
