<?php

/**
 * @file
 * Contains gatsby drush 8 commands.
 */

/**
 * Implements hook_drush_command().
 */
function gatsby_drush_command() {

  $commands['gatsby-fastbuilds-delete'] = [
    'description' => 'Deletes all the Gatsby Fastbuilds Log entries.',
    'aliases' => ['gatsdel'],
    'examples' => [
      'drush gatsdel' => 'Deletes all the Gatsby Fastbuilds Log entries.',
    ],
  ];

  return $commands;
}

/**
 * Deletes all the Gatsby Fastbuilds log entries.
 */
function drush_gatsby_fastbuilds_delete() {
  $time = time();
  \Drupal::service('gatsby.logger')
    ->deleteExpiredLoggedEntities($time);

  // Store the log time in order to validate future syncs.
  \Drupal::state()
    ->set('gatsby.last_logtime', $time);
}
