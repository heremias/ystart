<?php

namespace Drupal\static_suite\Lock;

// Use Drupal\Component\Utility\SignalHandler;.
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\static_suite\Utility\SignalHandler;

/**
 * A helper for the lock system.
 *
 * @ingroup lock
 */
class LockHelper implements LockHelperInterface {

  /**
   * The lock backend.
   *
   * @var \Drupal\Core\Lock\LockBackendInterface
   */
  protected LockBackendInterface $lock;

  /**
   * Constructs a new lock helper.
   *
   * @param \Drupal\Core\Lock\LockBackendInterface $lock
   *   The lock backend.
   */
  public function __construct(LockBackendInterface $lock) {
    $this->lock = $lock;

    // @todo - Remove registering shutdown callbacks once
    //   https://www.drupal.org/project/drupal/issues/3195300 is in place
    // Register shutdown callbacks to avoid stale semaphores on CLI.
    // PCNTL extension is usually loaded in CLI environments.
    if (extension_loaded('pcntl')) {
      // Create a closure over $this->releaseAll(), because the handler defined
      // in SignalHandler::register() receives parameters that conflict with
      // parameters of $this->releaseAll().
      $release_all_closure = function () {
        $this->lock->releaseAll();
      };

      // Define handled signals.
      // SIGHUP: hangup detected on controlling terminal or death of controlling
      //         process.
      // SIGINT: interrupt from keyboard (Ctrl+C)
      // SIGQUIT: quit from keyboard. (Ctrl+\ or kill -QUIT PID)
      // SIGTERM: termination signal. (kill -15 PID)
      // @see https://man7.org/linux/man-pages/man7/signal.7.html
      $signals = array_filter([
        defined('SIGHUP') ? SIGHUP : NULL,
        defined('SIGINT') ? SIGINT : NULL,
        defined('SIGQUIT') ? SIGQUIT : NULL,
        defined('SIGTERM') ? SIGTERM : NULL,
      ]);
      foreach ($signals as $signo) {
        SignalHandler::register($signo, $release_all_closure);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getLock(): LockBackendInterface {
    return $this->lock;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   */
  public function acquireOrWait(string $name, float $timeout = 30.0, int $delay = 30): bool {
    return (
      $this->lock->acquire($name, $timeout)
      || (
        !$this->lock->wait($name, $delay)
        && $this->lock->acquire($name, $timeout)
      )
    );
  }

}
