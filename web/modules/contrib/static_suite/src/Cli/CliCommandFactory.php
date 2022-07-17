<?php

namespace Drupal\static_suite\Cli;

use Drupal\static_suite\Cli\Result\CliCommandResultFactoryInterface;

/**
 * A factory that creates CliCommandInterface objects.
 */
class CliCommandFactory implements CliCommandFactoryInterface {

  /**
   * The CLI command result factory.
   *
   * @var \Drupal\static_suite\Cli\Result\CliCommandResultFactoryInterface
   */
  protected $cliCommandResultFactory;

  /**
   * Creates a new instance of CliCommandFactory.
   *
   * @param \Drupal\static_suite\Cli\Result\CliCommandResultFactoryInterface $cliCommandResultFactory
   *   The CLI command result factory.
   */
  public function __construct(CliCommandResultFactoryInterface $cliCommandResultFactory) {
    $this->cliCommandResultFactory = $cliCommandResultFactory;
  }

  /**
   * {@inheritdoc}
   */
  public function create(string $cmd, string $cwd = NULL, array $env = NULL): CliCommandInterface {
    return new CliCommand($this->cliCommandResultFactory, $cmd, $cwd, $env);
  }

}
