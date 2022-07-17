<?php

namespace Drupal\Tests\static_suite\Unit;

use Drupal\static_suite\Utility\SignalHandler;
use PHPUnit\Framework\TestCase;

/**
 * Tests signal handling.
 *
 * @todo - Remove this test once
 *   https://www.drupal.org/project/drupal/issues/3195300 is in place
 *
 * @coversDefaultClass \Drupal\static_suite\Utility\SignalHandler
 */
class SignalHandlerTest extends TestCase {

  /**
   * @var array
   *
   * Array to hold the execution order of signal handlers.
   *
   * Using a private property to be able to properly test the execution order
   * of handlers, not possible by using a mocking approach. Also, mocking does
   * not work well with signal handlers.
   */
  private $executedHandlers;

  /**
   * Utility test method to define a previous handler.
   */
  public function previousHandler(): void {
    $this->executedHandlers[] = 'previous';
  }

  /**
   * Utility test method to define a first handler.
   */
  public function handler1(): void {
    $this->executedHandlers[] = 'handler1';
  }

  /**
   * Utility test method to define a second handler.
   */
  public function handler2(): void {
    $this->executedHandlers[] = 'handler2';
  }

  /**
   * PHPUnit setup method.
   *
   * Checks that required extensions and functions are available, and cleans up
   * before each test runs.
   */
  public function setup(): void {
    if (!extension_loaded('pcntl')) {
      $this->markTestSkipped('The PCNTL extension is not available.');
    }

    if (!extension_loaded('posix')) {
      $this->markTestSkipped('The POSIX extension is not available.');
    }

    if (!function_exists('pcntl_signal')) {
      $this->markTestSkipped('The function "pcntl_signal" is not available.');
    }

    if (!function_exists('pcntl_signal_dispatch')) {
      $this->markTestSkipped('The function "pcntl_signal_dispatch" is not available.');
    }

    if (!function_exists('posix_kill')) {
      $this->markTestSkipped('The function "posix_kill" is not available.');
    }

    if (!function_exists('posix_getpid')) {
      $this->markTestSkipped('The function "posix_getpid" is not available.');
    }

    if (!defined('SIGCONT')) {
      $this->markTestSkipped('The constant "SIGCONT" is not available.');
    }

    // Clean up. Using SIGCONT because its default action (continue executing a
    // process, if stopped) will not stop test execution.
    SignalHandler::unregisterAll(SIGCONT);
    $this->executedHandlers = [];
  }

  /**
   * Tests handler registration.
   *
   * @covers ::register
   */
  public function testRegister(): void {
    // Register handlers.
    SignalHandler::register(SIGCONT, [$this, 'handler1']);
    SignalHandler::register(SIGCONT, [$this, 'handler2']);

    // Dispatch signal.
    posix_kill(posix_getpid(), SIGCONT);
    pcntl_signal_dispatch();

    $expected_result = [
      'handler1',
      'handler2',
    ];
    $this->assertEquals(
      $this->executedHandlers,
      $expected_result,
      sprintf(
        'Handlers not executed in the defined order. Expected "%s" but got "%s"',
        implode(', ', $expected_result),
        implode(', ', $this->executedHandlers)
      )
    );
  }

  /**
   * Tests unregister.
   *
   * @covers ::unregister
   */
  public function testUnregister(): void {
    // Register handlers.
    $handlerId1 = SignalHandler::register(SIGCONT, [$this, 'handler1']);
    SignalHandler::register(SIGCONT, [$this, 'handler2']);

    // Unregister one single handler.
    SignalHandler::unregister($handlerId1);

    // Dispatch signal.
    posix_kill(posix_getpid(), SIGCONT);
    pcntl_signal_dispatch();

    $expected_result = ['handler2'];
    $this->assertEquals(
      $this->executedHandlers,
      $expected_result,
      sprintf(
        'Handler not unregistered. Expected "%s" but got "%s"',
        implode(', ', $expected_result),
        implode(', ', $this->executedHandlers)
      )
    );
  }

  /**
   * Tests unregisterAll.
   *
   * @covers ::unregisterAll
   *
   * @requires function pcntl_signal_get_handler
   */
  public function testUnregisterAll(): void {
    $previousHandler = pcntl_signal_get_handler(SIGINT);

    // Register handlers.
    SignalHandler::register(SIGCONT, [$this, 'handler1']);
    SignalHandler::register(SIGCONT, [$this, 'handler2']);

    // Unregister all handlers.
    SignalHandler::unregisterAll(SIGCONT);

    // Dispatch signal.
    posix_kill(posix_getpid(), SIGCONT);
    pcntl_signal_dispatch();

    $expected_result = [];
    $this->assertEquals(
      $this->executedHandlers,
      $expected_result,
      sprintf(
        'Expected all handlers to be unregistered but got "%s"',
        implode(', ', $this->executedHandlers)
      )
    );

    $restoredHandler = pcntl_signal_get_handler(SIGINT);
    $this->assertEquals(
      $previousHandler,
      $restoredHandler,
      sprintf(
        'Expected the previous handler to be restored after calling unregisterAll(), but got "%s"',
        $restoredHandler
      )
    );
  }

  /**
   * Tests preventPrevious.
   *
   * @covers ::preventPrevious
   */
  public function testPreventPrevious(): void {
    // Register a previous handler. Must be done using a closure over
    // $this->previousHandler() instead [$this, 'previousHandler'] to avoid
    // PHPUnit losing the reference to $this.
    $previous_handler_closure = function () {
      $this->previousHandler();
    };
    pcntl_signal(SIGCONT, $previous_handler_closure);

    // Ensure prevent previous handler will be executed.
    SignalHandler::preventPrevious(SIGCONT, FALSE);

    // Register handlers.
    SignalHandler::register(SIGCONT, [$this, 'handler1']);
    SignalHandler::register(SIGCONT, [$this, 'handler2']);

    // Dispatch signal.
    posix_kill(posix_getpid(), SIGCONT);
    pcntl_signal_dispatch();

    $expected_result1 = [
      'handler1',
      'handler2',
      'previous',
    ];
    $this->assertEquals(
      $this->executedHandlers,
      $expected_result1,
      sprintf(
        'Previous handler not executed. Expected "%s" but got "%s"',
        implode(', ', $expected_result1),
        implode(', ', $this->executedHandlers)
      )
    );

    $this->executedHandlers = [];

    // Prevent previous handler to be executed.
    SignalHandler::preventPrevious(SIGCONT, TRUE);

    // Dispatch signal.
    posix_kill(posix_getpid(), SIGCONT);
    pcntl_signal_dispatch();

    $expected_result2 = [
      'handler1',
      'handler2',
    ];
    $this->assertEquals(
      $this->executedHandlers,
      $expected_result2,
      sprintf(
        'Previous handler executed. Expected "%s" but got "%s"',
        implode(', ', $expected_result2),
        implode(', ', $this->executedHandlers)
      )
    );
  }

}
