<?php

namespace Drupal\static_builder_codebuild\Plugin\static_build\StaticBuilder;

use Aws\CloudWatchLogs\CloudWatchLogsClient;
use Aws\CodeBuild\CodeBuildClient;
use Aws\Credentials\Credentials;
use Drupal\Core\Site\Settings;
use Drupal\static_build\Plugin\StaticBuilderPluginBase;
use Drupal\static_suite\StaticSuiteException;

/**
 * Provides a static builder for AWS Codebuild.
 *
 * @StaticBuilder(
 *  id = "codebuild",
 *  label = @Translation("AWS CodeBuild"),
 *  description = @Translation("Static builder that uses AWS Codebuild"),
 *  host = "cloud"
 * )
 */
class CodeBuildStaticBuilder extends StaticBuilderPluginBase {

  /**
   * {@inheritdoc}
   */
  public function build(): void {
    $credentials = new Credentials($this->configuration['access-key'], $this->configuration['secret-key']);
    $clientConfig = [
      'version' => 'latest',
      'region' => $this->configuration['region'],
      'credentials' => $credentials,
    ];
    // Set up clients.
    $codeBuildClient = new CodeBuildClient($clientConfig);
    $cloudWatchLogsClient = new CloudWatchLogsClient($clientConfig);

    // Start build.
    $result = $codeBuildClient->startBuild([
      'projectName' => $this->configuration['project'],
    ]);
    $buildId = $result['build']['id'];
    $this->logMessage('Build started with id: ' . $buildId);
    $this->logMessage('Waiting for AWS Codebuild logs... ');

    // Check status until "currentPhase" is "COMPLETED".
    $logsLastKey = 0;
    while (TRUE) {
      // Get info about the running build.
      $result = $codeBuildClient->batchGetBuilds(['ids' => [$buildId]]);
      $logGroupName = $result['builds'][0]['logs']['groupName'] ?? NULL;
      $logStreamName = $result['builds'][0]['logs']['streamName'] ?? NULL;

      // Get logs if $logGroupName and $logStreamName are present.
      $logsSaved = FALSE;
      if ($logGroupName && $logStreamName) {
        $logs = $cloudWatchLogsClient->getLogEvents([
          'logGroupName' => $logGroupName,
          'logStreamName' => $logStreamName,
        ]);

        // Save logs progressively as they became available.
        if (!empty($logs['events']) && is_array($logs['events'])) {
          $newLogEvents = array_slice($logs['events'], $logsLastKey);
          $logsLastKey = count($logs['events']);
          foreach ($newLogEvents as $logEvent) {
            $this->logMessage(trim($logEvent['message']), FALSE);
            $logsSaved = TRUE;
          }
        }
      }

      if (!$logsSaved) {
        $this->logMessage('Waiting for AWS Codebuild logs...');
      }

      // Break the loop if build has finished.
      if (!empty($result['builds'][0]['buildComplete']) && $result['builds'][0]['buildComplete'] === TRUE) {
        $this->logMessage('Build completed');
        // Get current status.
        if (!empty($result['builds'][0]['buildStatus'])) {
          $buildStatus = $result['builds'][0]['buildStatus'];
          $this->logMessage('Build status: ' . $buildStatus);
          if (in_array($buildStatus, [
            'FAILED',
            'FAULT',
            'TIMED_OUT',
            'STOPPED',
          ])) {
            throw new StaticSuiteException('Build status: ' . $buildStatus);
          }
        }
        break;
      }

      // Wait 5 seconds to check status again.
      sleep(5);
    }

  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    $config = $this->configFactory->get('static_builder_codebuild.settings');
    return parent::defaultConfiguration() + [
      'project' => $config->get('project'),
      'region' => $config->get('region'),
      'access-key' => Settings::get('static_builder_codebuild.access_key'),
      'secret-key' => Settings::get('static_builder_codebuild.secret_key'),
    ];
  }

}
