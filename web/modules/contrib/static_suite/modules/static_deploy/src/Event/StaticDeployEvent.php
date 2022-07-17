<?php

namespace Drupal\static_deploy\Event;

use Drupal\static_deploy\Plugin\StaticDeployerPluginInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Generic static deploy event to be fired.
 */
class StaticDeployEvent extends Event {

  /**
   * The static deployer that triggers the event.
   *
   * @var \Drupal\static_deploy\plugin\StaticDeployerPluginInterface
   */
  protected $staticDeployer;

  /**
   * An array with structured data.
   *
   * @var array
   */
  protected $data;

  /**
   * Constructs the object.
   *
   * @param \Drupal\static_deploy\plugin\StaticDeployerPluginInterface $staticDeployer
   *   The static deployer.
   */
  public function __construct(StaticDeployerPluginInterface $staticDeployer) {
    $this->staticDeployer = $staticDeployer;
  }

  /**
   * Get the static deployer.
   *
   * @return \Drupal\static_deploy\plugin\StaticDeployerPluginInterface
   *   The static deployer.
   */
  public function getStaticDeployer(): StaticDeployerPluginInterface {
    return $this->staticDeployer;
  }

  /**
   * Set event data.
   *
   * @param array $data
   *   An array with data.
   */
  public function setData(array $data): void {
    $this->data = $data;
  }

  /**
   * Get event data.
   *
   * @return array
   *   The event data.
   */
  public function getData(): array {
    return $this->data;
  }

}
