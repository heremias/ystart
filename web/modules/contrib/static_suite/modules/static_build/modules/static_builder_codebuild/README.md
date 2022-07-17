# AWS Codebuild Static Builder
This module is a plugin for Static Build module and builds a static site using
[AWS Codebuild](https://aws.amazon.com/codebuild/).

## INTRODUCTION ##
Since AWS Codebuild is a CI/CD service, it must handle not only building your
site, but also deploying it to the CDN/hosting service of your choice.
Therefore, how AWS Codebuild behaves is completely up to the way you configure
it. See a working example with Gatsby at the end of this document.

Anyway, AWS Codebuild (and all builders running on a CI/CD services) need
access to data exported by Static Export. It's located at
`[BASE_DIRECTORY]/codebuild/live/.build/data.tar` and updated every time a
build is triggered. AWS Codebuild needs to get that file from the server
running your Drupal instance. See a working example at the end of this
document.

## REQUIREMENTS ##
AWS PHP SDK (automatically installed when using composer)

## INSTALLATION ##
Run `composer require drupal/static_builder_codebuild`.

Follow the instructions available at `/admin/config/static/build`, and
create the directory structure as stated in that configuration page.

## CONFIGURATION ##
There are two configuration types involved in this module.
* global configuration for the Static Build module:
  `/admin/config/static/build`
* AWS Codebuild configuration: `/admin/config/static/build/codebuild`

## Sample buildspec.yml: Gastby + deployment to AWS S3 ##

```
version: 0.2

# Save SSH KEY to access your Drupal instance in AWS Parameter Store
env:
  parameter-store:
    drupal_ssh_key: '/drupal-ssh-key/my-user'

phases:
  install:
    runtime-versions:
      nodejs: 12
    commands:
      # SSH setup
      - mkdir -p ~/.ssh
      - echo "$drupal_ssh_key" > ~/.ssh/id_rsa
      - chmod 600 ~/.ssh/id_rsa
      - ssh-keygen -F $DRUPAL_IP || ssh-keyscan $DRUPAL_IP >>~/.ssh/known_hosts
      # Install node dependencies
      - yarn install

  pre_build:
    commands:
      # Create a global build directory to ensure compilation hash is always the same
      - cp -a $CODEBUILD_SRC_DIR /build-directory
      # Data copy
      - scp my-user@$DRUPAL_IP:/path/to/codebuild/live/.build/data.tar /build-directory/data.tar
      - mkdir /build-directory/data
      - cd /build-directory/data
      - tar -xf ../data.tar

      # Configure build. This could be done in different ways, but using a .env file is a best practice
      - printf "GATSBY_DATA_SOURCE=data\nGATSBY_CONTENT_TYPES_TO_BUILD=$GATSBY_CONTENT_TYPES_TO_BUILD" > /build-directory/.env

  build:
    commands:
      - 'yarn build'

  # Upload artifacts using https://packagist.org/packages/asilgag/aws-s3-incremental-deployer, suitable for large sites.
  post_build:
    commands:
      # Install composer
      - php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
      - php composer-setup.php ;
      - php -r "unlink('composer-setup.php');" ;
      - mv composer.phar /usr/local/bin/composer

      # Install composer dependencies
      - cd /build-directory/codebuild/deploy
      - composer install

      # Execute deploy-s3.php program (source code available in this README file)
      - php -q deploy-s3.php --source-dir=/build-directory/public/ --bucket=my-bucket --region=us-east-1

```

## Sample composer.json for deploy-s3.php program ##
```
{
    "name": "my-project/aws-s3-incremental-deployer",
    "type": "project",
    "require": {
        "asilgag/aws-s3-incremental-deployer": "^1.0"
    }
}
```

## Sample deploy-s3.php program ##
```
<?php
use Asilgag\AWS\S3\AwsS3IncrementalDeployer;
use Asilgag\AWS\S3\Logger\MultiLogger;

require __DIR__ . '/vendor/autoload.php';

/*
 * USAGE:
 * export AWS_ACCESS_KEY_ID=***; export AWS_SECRET_ACCESS_KEY=***
 * php -q deploy-s3.php --source-dir=/path/to/codebuild/live/current/ --bucket=my-bucket --region=us-east-1
 */

$longOpts  = [
  'source-dir:',
  'bucket:',
  'region:',
];
$options = getopt('', $longOpts);
if (empty($options['source-dir'])) {
  echo "Missing required option: --source-dir\n";
  exit(1);
}
if (empty($options['bucket'])) {
  echo "Missing required option: --bucket\n";
  exit(1);
}
if (empty($options['region'])) {
  echo "Missing required option: --region\n";
  exit(1);
}

$options['source-dir'] = rtrim($options['source-dir'], '/');

$multiLogger = new MultiLogger(NULL, TRUE);
$s3SiteDeployer = new AwsS3IncrementalDeployer($multiLogger);
$s3SiteDeployer->setExcludedPaths(['.metadata/tasks/*']);
$s3SiteDeployer->getAwsCli()->getAwsOptions()->add('--region ' . $options['region']);
$s3SiteDeployer->deploy($options['source-dir'], $options['bucket']);

```
