<?php

namespace Drupal\static_deploy\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\static_deploy\Plugin\StaticDeployerPluginManagerInterface;
use Exception;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provides a controller to execute a new deploy on demand.
 */
class DeployerController extends ControllerBase {

  /**
   * Request stack.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * Static deployer manager.
   *
   * @var \Drupal\static_deploy\Plugin\StaticDeployerPluginManagerInterface
   */
  protected $staticDeployerPluginManager;

  /**
   * BuilderController constructor.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   Request stack.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   Language Manager.
   * @param \Drupal\static_deploy\Plugin\StaticDeployerPluginManagerInterface $staticDeployerPluginManager
   *   The static deployer manager.
   */
  public function __construct(
    RequestStack $requestStack,
    LanguageManagerInterface $languageManager,
    StaticDeployerPluginManagerInterface $staticDeployerPluginManager
  ) {
    $this->request = $requestStack->getCurrentRequest();
    $this->languageManager = $languageManager;
    $this->staticDeployerPluginManager = $staticDeployerPluginManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): DeployerController {
    return new static(
      $container->get('request_stack'),
      $container->get('language_manager'),
      $container->get('plugin.manager.static_deployer')
    );
  }

  /**
   * Run a new deployment.
   *
   * @param string $deployerId
   *   Static Deployer Id.
   * @param string $builderId
   *   Static Builder Id.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Redirect to previous page.
   */
  public function runDeploy(string $deployerId, string $builderId): RedirectResponse {
    try {
      $this->executeDeploy($deployerId, $builderId);

      $message = $this->t('A new deploy for "@deployerId" / "@builderId" has started.', [
        '@deployerId' => $deployerId,
        '@builderId' => $builderId,
      ]);
    }
    catch (Exception $e) {
      $message = $e->getMessage();
    }

    $this->messenger()->addMessage($message);

    // Wait 2 seconds for the process to start to be redirected.
    sleep(2);
    return new RedirectResponse($this->request->query->get('destination'));
  }

  /**
   * Execute a new deployment.
   *
   * @param string $deployerId
   *   Static Deployer Id.
   * @param string $builderId
   *   Static Builder Id.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\static_suite\StaticSuiteException
   * @throws \Drupal\static_suite\StaticSuiteUserException
   */
  protected function executeDeploy(string $deployerId, string $builderId): void {
    $plugin = $this->staticDeployerPluginManager->getInstance([
      'plugin_id' => $deployerId,
      'configuration' => [
        'builder-id' => $builderId,
        'force' => TRUE,
      ],
    ]
    );
    $plugin->init();
  }

}
