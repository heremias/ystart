# Static Deploy
Base module that enables the deployment of sites built by Static Build to
different CDN/hosting services.

## INTRODUCTION ##
This module serves as an orchestrator for the process of deploying a site,
and should be complemented with a compatible "static deployer" module.

At this moment, you can install any of the following static deployers:
* [AWS S3](https://www.drupal.org/project/static_deployer_s3)

## ADDING CUSTOM DEPLOYERS ##
Static deployers are plugins that must follow these rules:
* Be annotated with @StaticDeployer annotation
* Implement Drupal\static_deploy\Plugin\StaticDeployerInterface
* Declare a route for editing configuration, named after this rule:
  static_deployer_{PLUGIN_ID}.settings

Example (static_deployer_{PLUGIN_ID}.routing.yml):
```
static_deployer_{PLUGIN_ID}.settings:
path: '/admin/config/static/deploy/{PLUGIN_ID}'
defaults:
  _form: \Drupal\static_deployer_{PLUGIN_ID}\Form\SettingsForm
  _title: 'Static Deployer - {PLUGIN_ID}: Settings'
requirements:
  _permission: 'administer site configuration'
```
## INSTALLATION ##
Run `composer require drupal/static_deploy`

## REQUIREMENTS ##
It depends on Static Build module.

## CONFIGURATION ##
Configuration available at /admin/config/static/deploy.
