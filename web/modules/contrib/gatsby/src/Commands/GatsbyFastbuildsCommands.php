<?php

namespace Drupal\gatsby\Commands;

use Drush\Commands\DrushCommands;
use Drupal\Core\State\StateInterface;
use Drupal\gatsby\GatsbyEntityLogger;

/**
 * A drush command file.
 *
 * @package Drupal\gatsby\Commands
 */
class GatsbyFastbuildsCommands extends DrushCommands {

  /**
   * Drupal\gatsby\GatsbyEntityLogger definition.
   *
   * @var \Drupal\gatsby\GatsbyEntityLogger
   */
  protected $gatsbyLogger;

  /**
   * Drupal\Core\State\StateInterface definition.
   *
   * @var \Drupal\State\State\StateInterface
   */
  protected $state;

  /**
   * Constructs a new GatsbyFastbuildsCommands object.
   */
  public function __construct(
      GatsbyEntityLogger $gatsby_logger,
      StateInterface $state) {
    parent::__construct();
    $this->gatsbyLogger = $gatsby_logger;
    $this->state = $state;
  }

  /**
   * Drush command that deletes all the Gatsby Fastbuilds Log entries.
   *
   * @command gatsby:logs:purge
   * @aliases gatsdel gatsby_fastbuilds:delete
   * @usage gatsby:logs:purge
   */
  public function delete() {
    // @todo Add some logging on this, particularly to Drush.
    $this->gatsbyLogger->deleteExpiredLoggedEntities(time());

    // Store the log time in order to validate future syncs.
    $this->state->set('gatsby.last_logtime', $time);
  }

}
