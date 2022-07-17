# Static Deployer for AWS S3

Deploys sites built by Static Build module to a AWS S3 bucket.

## INTRODUCTION ##
This module is one of the multiple deployers available for Static Deploy.
You should install and configure Static Deploy before using this module.

Internally, it uses [asilgag/aws-s3-incremental-deployer](https://packagist.org/packages/asilgag/aws-s3-incremental-deployer)
to be able to execute incremental deploys in just a matter of seconds, even
for sites containing thousands of files.

## REQUIREMENTS ##
* Dependencies and requirements from
  [asilgag/aws-s3-incremental-deployer](https://packagist.org/packages/asilgag/aws-s3-incremental-deployer)
* A working AWS S3 bucket
* A valid AWS account and credentials (access and secret key)
* The following ACL permissions granted for the user performing the deployment:
  * READ
  * WRITE
  * READ_ACP

## INSTALLATION ##
Run `composer require drupal/static_deployer_s3`.

## CONFIGURATION ##
Configuration available at /admin/config/static/deploy/s3.
