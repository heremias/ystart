CONTENTS OF THIS FILE
---------------------

 * Introduction
 * Requirements
 * Installation
 * Configuration
 * Maintainers


INTRODUCTION
------------

The module lets you trigger notifications depending on the current status of
your configuration in production.

Notifications can be triggered via cron or instantly. Slack and email are
currently supported.

 * For a full description of the module visit:
   https://www.drupal.org/project/config_notify

 * To submit bug reports and feature suggestions, or to track changes visit:
   https://www.drupal.org/project/issues/config_notify


REQUIREMENTS
------------

This module requires the following modules.

* `config` core module needs to be enabled.
* `slack` not required, but if enabled then slack notifications will be 
supported.


INSTALLATION
------------

Install the Config Notify module as you would normally install a contributed
Drupal module. Visit https://www.drupal.org/node/1897420 for further
information.



CONFIGURATION
-------------

1. Navigate to `Administration > Extend` and enable the module.
2. Navigate to `Administration > Configuration > Development > Configuration
synchronisation > Notify tab` and tick the checkboxes based on your needs.
3. Then click on Save configuration or Notify now.



MAINTAINERS
-----------

 * Fran Garcia-Linares (fjgarlin) - https://www.drupal.org/u/fjgarlin
