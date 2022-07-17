<?php

namespace Drupal\static_deployer_s3\Plugin\static_deploy\StaticDeployer;

use Asilgag\AWS\S3\AwsS3IncrementalDeployer;
use Asilgag\AWS\S3\Logger\MultiLogger;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Site\Settings;
use Drupal\static_deploy\Plugin\StaticDeployerPluginBase;
use Drupal\static_suite\Release\ReleaseInterface;

/**
 * Provides a static deployer for S3 buckets.
 *
 * Internally, it uses aws cli to perform operations.
 *
 * @StaticDeployer(
 *  id = "s3",
 *  label = @Translation("AWS S3"),
 *  description = @Translation("Static Deployer for AWS S3 buckets.")
 * )
 */
class StaticDeployerS3 extends StaticDeployerPluginBase {

  /**
   * {@inheritdoc}
   *
   * Use S3SiteDeployer to handle all the logic to get incremental deploys.
   */
  public function deploy(): void {
    $configuration = $this->getConfiguration();
    $multiLogger = new MultiLogger($this->releaseTask->getLogFilePath(), (bool) $configuration['console-output']);
    $s3SiteDeployer = new AwsS3IncrementalDeployer($multiLogger);
    $s3SiteDeployer->setExcludedPaths([ReleaseInterface::TASKS_DIR . '/*']);
    $s3SiteDeployer->getAwsCli()
      ->getAwsOptions()
      ->add('--region ' . $configuration['region']);
    $s3SiteDeployer->getAwsCli()
      ->setEnvironment('AWS_ACCESS_KEY_ID', $configuration['access-key']);
    $s3SiteDeployer->getAwsCli()
      ->setEnvironment('AWS_SECRET_ACCESS_KEY', $configuration['secret-key']);
    $s3SiteDeployer->deploy($this->currentRelease->getDir(), $configuration['bucket']);
  }

  /**
   * {@inheritdoc}
   */
  public function rollback(): void {
    // No rollback possible on S3.
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return NestedArray::mergeDeep(
      parent::defaultConfiguration(), [
        'bucket' => $this->configFactory->get('static_deployer_s3.settings')
          ->get('bucket'),
        'region' => $this->configFactory->get('static_deployer_s3.settings')
          ->get('region'),
        'access-key' => Settings::get('static_deployer_s3.access_key'),
        'secret-key' => Settings::get('static_deployer_s3.secret_key'),
      ]
    );
  }

}
