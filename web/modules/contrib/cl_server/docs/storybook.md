# Add Storybook to your Drupal Site

There are two parts you need to configure to set up Storybook in Drupal. This is a one time setup for your Drupal site. After this, all the Storybook documentation will apply to your project. You can add addons, write stories, etc. provided they are compatible with Storybook's server framework.

## The Drupal Part

If you don't have a Drupal site install one from scratch.

```console
composer create "drupal/recommended-project" --no-interaction
composer require "drush/drush" --no-interaction
drush site:install --account-name=[REDACTED] --account-pass=[REDACTED] --yes
```

Change the default themes _Bartik_ → _Olivero_ and _Seven_ → _Claro_. This is not required for Storybook integration.

If you haven't already, change the minimum stability to `dev` inside `composer.json`. This is so we can install the Drupal modules that don't have, yet, a stable release.

Next we need to install the Drupal modules.
```console
composer require "drupal/cl_server" "drupal/cl_components"
drush pm:enable --yes cl_server
```

Next we need to enable and update `development.services.yml`. This tutorial assumes you start your site from scratch. If you already have enabled `settings.local.php` and development services, you can skip this step.

```console
# And enable local settings. Instructions are at the bottom of the file.
vim web/sites/default/settings.php

cp web/sites/example.settings.local.php web/sites/default/settings.local.php
# Here disable caches (use the null cache).
# Then disallow exporting config for 'cl_server'. Instructions are at the bottom of the file.
vim web/sites/default/settings.local.php

# And add Twig and Cors settings below.
vim web/sites/development.services.yml
```

In `development.services.yml` you want to add some configuration for Twig, so you don't need to clear caches so often. This is not needed for the Storybook integration, but it will make things easier when you need to move components to your Drupal templates.

You also need to enable CORS. You want this CORS configuration to be in `development.services.yml` so it does not get changed in your production environment. Remember _CL Server_ **SHOULD** be disabled in production.

The configuration you want looks like this:

```yaml
parameters:
  # ...
  twig.config:
    debug: true
    cache: false
  cors.config:
    enabled: true
    allowedHeaders: ['*']
    allowedMethods: []
    allowedOrigins: ['*']
    exposedHeaders: false
    maxAge: false
    supportsCredentials: true
services:
  # ...
```

Here you clear caches to have the dependency container pick up on the new config.

```console
drush cache:rebuild
```

## The Storybook Part

Now it's time to install Storybook. You'll need `yarn`. Docs are at: https://storybook.js.org/addons/@lullabot/storybook-drupal-addon

Before you can install anything you'll need to do initialize the `package.json`. Then you can install the Drupal addon.

```console
yarn init
yarn add -D @lullabot/storybook-drupal-addon
```

### 🌴 Add Storybook to your Drupal repo
From the root of your repo:

```console
yarn global add sb@latest;
sb init --builder webpack5 --type server
# If you have a reason to use Webpack4 use the following instead:
# sb init --type server
yarn add -D @lullabot/storybook-drupal-addon
```
### 🌵 Configure Storybook
First enable the addon. Add it to the `addons` in the `.storybook/main.js`.

```javascript
// .storybook/main.js
module.exports = {
  // ...
  addons: [
    // ...
    '@lullabot/storybook-drupal-addon',
  ],
  // ...
}
```
Then, configure the `supportedDrupalThemes` and `drupalTheme` parameters in `.storybook/preview.js`.

`supportedDrupalThemes` is an object where the keys are the machine name of the Drupal themes and the values are the plain text name of that Drupal theme you want to use. This is what will appear in the dropdown in the toolbar.

```javascript
// .storybook/preview.js
export const parameters = {
  // ...
  server: {
    // Replace this with your Drupal site URL, or an environment variable.
    url: 'http://local.contrib.com',
  },
  drupalTheme: 'olivero',
  supportedDrupalThemes: {
    olivero: {title: 'Olivero'},
    claro: {title: 'Claro'},
  },
  // ...
};
```

## Start Storybook

Start the Storybook's development server:

```console
yarn storybook
```

Storybook will start and you will see a black and red screen with an error. This is because you need to set the Components config in Drupal. You need to visit `/admin/config/user-interface/cl-components` in your Drupal site. Add `modules/contrib/cl_components/examples` in the _Paths_ textarea and save. This will make Drupal aware of the example components in _CL Components_ module.

You'll refresh and you will see another error. This is a 403 because you need to allow the CL Server render endpoint for the anonymous user. So you need to go to the Drupal permissions page and grant permission to the anonymous user to access the component rendering endpoint. Note that this permission will not be exported into configuration because you excluded `cl_server` above in `settings.local.php`.

Now you can restart the Storybook server. Kill the `yarn storybook` process and run it again.
