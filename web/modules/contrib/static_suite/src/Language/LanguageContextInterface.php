<?php

namespace Drupal\static_suite\Language;

/**
 * Interface to execute a callable in a defined language context.
 */
interface LanguageContextInterface {

  /**
   * Executes a callable in a defined language context.
   *
   * @param callable $callable
   *   The callable to be executed.
   * @param string $langcode
   *   The langcode to be set.
   *
   * @return mixed|void
   *   The callable's result.
   *
   * @throws \Exception
   *   Any exception caught while executing the callable.
   */
  public function executeInLanguageContext(callable $callable, string $langcode);

}
