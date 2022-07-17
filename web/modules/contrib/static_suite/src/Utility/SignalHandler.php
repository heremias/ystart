<?php

namespace Drupal\static_suite\Utility;

/**
 * Provides signal handling related utility functions.
 *
 * @todo - Remove this handler once
 *   https://www.drupal.org/project/drupal/issues/3195300 is in place
 */
class SignalHandler {

  protected static $signals = [];

  /**
   * Register a handler for a given POSIX signal.
   *
   * This function stacks several handlers so they are executed in the same
   * order as registered. It allows registering multiple handlers for the same
   * signal, and being sure that all of them will get executed. That is not
   * possible with pcntl_signal(), where only the last registered one is
   * executed.
   *
   * Example with pcntl_signal():
   * cSpell:disable
   *
   * @code
   *    // Assume having two signal handlers. The first one is not executed.
   *    pcntl_signal(SIGINT, [$this, 'firstHandler']);
   *    pcntl_signal(SIGINT, [$this, 'secondHandler']);
   * @endcode
   * cSpell:enable
   *
   * Since only the last registered handler is executed, this would lead to
   * unexpected results if several modules or components register a handler for
   * the same signal. This utility solves that problem.
   *
   * Example with SignalHandler::register()
   * cSpell:disable
   * @code
   *    // Assume having two signal handlers. Both will be executed.
   *    $handlerId1 = SignalHandler::register(SIGINT, [$this, 'firstHandler']);
   *    $handlerId2 = SignalHandler::register(SIGINT, [$this, 'secondHandler']);
   * @endcode
   * cSpell:enable
   *
   * There are two special handlers that cannot be added to the stack, which is
   * a key difference from pcntl_signal():
   *  - SIG_IGN: ignore the signal. This disables handling signals in a global
   *             way. If SIG_IGN is needed, pcntl_signal() must be used instead.
   *  - SIG_DFL: restore the default signal handler (usually stopping or pausing
   *             the process). To do so, use ::unregister() or ::unregisterAll()
   *             to remove all manually added handlers.
   *
   * @param int $signal
   *   The signal number.
   * @param callable $handler
   *   A callable, which will be invoked to handle the signal. It must implement
   *   the following signature:
   *   cSpell:disable
   *   handler ( int $signo , mixed $siginfo ) : void
   *     signo
   *       The signal being handled.
   *     siginfo
   *       If operating system supports siginfo_t structures, this will be an
   *       array of signal information dependent on the signal.
   *   cSpell:enable.
   * @param bool $restart_system_calls
   *   Specifies whether system call restarting should be used when this signal
   *   arrives.
   *
   * @return string|null
   *   The id of the registered handler on success, or null if the handler
   *   cannot be registered.
   * @see https://www.php.net/manual/en/function.pcntl-signal.php
   */
  public static function register(int $signal, callable $handler, bool $restart_system_calls = TRUE): ?string {
    if (!function_exists('pcntl_signal')) {
      return NULL;
    }

    // Get a handler id based on its signal number and creation time.
    [$msec, $sec] = explode(" ", microtime());
    $handler_id = $signal . '--' . $sec . substr($msec, 1);

    // Get the previous handler only once for each signal. Its execution can be
    // disabled by calling ::preventPrevious($signal).
    if (!isset(static::$signals[$signal]['previous'])) {
      if (function_exists('pcntl_signal_get_handler')) {
        static::$signals[$signal]['previous'] = pcntl_signal_get_handler($signal);
      }
      elseif (defined('SIG_DFL')) {
        static::$signals[$signal]['previous'] = SIG_DFL;
      }
    }

    // Save handler in the stack.
    static::$signals[$signal]['handlers'][$handler_id] = $handler;
    static::$signals[$signal]['restart_system_calls'] = $restart_system_calls;

    if (function_exists('pcntl_async_signals')) {
      pcntl_async_signals(TRUE);
    }

    // pcntl_signal needs a function or a public method for its second argument.
    // To avoid making ::handleSignal() public when it should be protected, use
    // a closure over ::handleSignal().
    $handle_signal_closure = function (int $signal, array $siginfo = NULL) {
      static::handleSignal($signal, $siginfo);
    };
    pcntl_signal($signal, $handle_signal_closure, $restart_system_calls);

    // Return the handler id so it can be later unregistered.
    return $handler_id;
  }

  /**
   * Unregister a previously registered handler.
   *
   * If only one handler is registered, it calls unregisterAll() to restore the
   * previous signal handler.
   *
   * @param string $handler_id
   *   The id of the handler to be unregistered. This id is returned by
   *   ::register()
   *
   *   Example:
   *   cSpell:disable
   *
   * @code
   *   $myHandlerId = SignalHandler::register(SIGINT, [$this, 'myHandler']);
   *   SignalHandler::unregister($myHandlerId);
   * @endcode
   * cSpell:enable
   */
  public static function unregister(string $handler_id): void {
    // Get signal number from $handler_id.
    [$signal] = explode('--', $handler_id);
    if (isset(static::$signals[$signal]['handlers'][$handler_id])) {
      // If there is only one handler, call unregisterAll() to restore the
      // previous signal handler.
      if (count(static::$signals[$signal]['handlers']) === 1) {
        static::unregisterAll($signal);
      }
      else {
        unset(static::$signals[$signal]['handlers'][$handler_id]);
      }
    }
  }

  /**
   * Unregister all handlers and related data for a given signal.
   *
   * @param int $signal
   *   The signal number.
   */
  public static function unregisterAll(int $signal): void {
    if (isset(static::$signals[$signal])) {
      // Get previous handler, if any.
      if (isset(static::$signals[$signal]['previous'])) {
        $previous = static::$signals[$signal]['previous'];
      }
      elseif (defined('SIG_DFL')) {
        $previous = SIG_DFL;
      }
      else {
        $previous = NULL;
      }
      unset(static::$signals[$signal]);

      if ($previous !== NULL && function_exists('pcntl_signal')) {
        // pcntl_signal() uses a third parameter, $restart_system_calls. Its
        // default value is true. Since there is no way to know if a previous
        // handler was set using $restart_system_calls with a value of true or
        // false, keep its default value (true).
        pcntl_signal($signal, $previous);
      }
    }
  }

  /**
   * Indicate whether an existing previous handler should be ignored or not.
   *
   * Before any handler is added to the stack, signals are already being
   * handled by their default handler (unless pcntl_signal() has been called).
   * This function indicates whether that handler should be ignored or not. By
   * default, that handler is not ignored so it is also executed after the stack
   * of handlers.
   *
   * Use this function, for example, if you need to handle a signal and do not
   * want the process to be stoppable. Example:
   * cSpell:disable
   *
   * @code
   *   // $this->myHandler() will handle the SIGINT signal, but process will not
   *   // be interrupted.
   *   SignalHandler::preventPrevious(SIGINT);
   *   $myHandlerId = SignalHandler::register(SIGINT, [$this, 'myHandler']);
   * @endcode
   * cSpell:enable
   *
   * @param int $signal
   *   The signal number.
   * @param bool $flag
   *   A flag to indicate whether previous handler should be ignored.
   *   Defaults to TRUE.
   */
  public static function preventPrevious(int $signal, bool $flag = TRUE): void {
    static::$signals[$signal]['prevent_previous'] = $flag;
  }

  /**
   * Handles a signal.
   *
   * This function is called whenever a registered signal is dispatched. It
   * first executes all stacked handlers, and then the previous handler (if any)
   * depending on the value set by ::preventPrevious()
   *
   * @param int $signal
   *   The signal number.
   * @param array|null $siginfo
   *   An optional array of signal information dependent on the signal.
   *
   * @internal
   */
  public static function handleSignal(int $signal, array $siginfo = NULL): void {
    // Execute all handlers in the stack.
    if (isset(static::$signals[$signal]['handlers']) && is_array(static::$signals[$signal]['handlers'])) {
      foreach (static::$signals[$signal]['handlers'] as $handler) {
        if (is_callable($handler)) {
          $handler($signal, $siginfo);
        }
      }
    }

    // Check whether previous handler should be executed.
    $prevent_previous = static::$signals[$signal]['prevent_previous'] ?? FALSE;
    $previous = static::$signals[$signal]['previous'] ?? NULL;
    if ($prevent_previous || $previous === NULL || (defined('SIG_IGN') && $previous === SIG_IGN)) {
      return;
    }

    // If previous handler is SIG_DFL, temporarily install a new handler using
    // pcntl_signal(), and then manually dispatch a SIG_DFL signal. As a last
    // step, restore our original handler.
    if (defined('SIG_DFL') && $previous === SIG_DFL) {
      if (function_exists('pcntl_signal') &&
        function_exists('pcntl_signal_dispatch') &&
        function_exists('posix_getpid') &&
        function_exists('posix_kill')) {
        $restart_system_calls = static::$signals[$signal]['restart_system_calls'] ?? FALSE;
        pcntl_signal($signal, SIG_DFL, $restart_system_calls);
        $old_set = [];
        if (function_exists('pcntl_sigprocmask') && defined('SIG_UNBLOCK')) {
          pcntl_sigprocmask(SIG_UNBLOCK, [$signal], $old_set);
        }
        posix_kill(posix_getpid(), $signal);
        pcntl_signal_dispatch();
        if (function_exists('pcntl_sigprocmask') && defined('SIG_SETMASK') && $old_set) {
          pcntl_sigprocmask(SIG_SETMASK, $old_set);
        }
        pcntl_signal($signal, [
          __CLASS__,
          'handleSignal',
        ], $restart_system_calls);
      }
    }
    elseif (is_callable($previous)) {
      $previous($signal, $siginfo);
    }
  }

}
