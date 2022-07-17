# Gatsby Static Builder
This module is a plugin for Static Build module and builds a static site using
[Gatsby](https://www.gatsbyjs.org/).

## INTRODUCTION ##
It works by spawning a background process which actually runs a `gatsby build`
command on a bash shell.

That process is run by the user running the web server (usually `www-data` or
similar) so you must ensure that user can run a `gatsby build` command (that
means having `gatsby` and `node` installed and available in that user's `$PATH`
environment variable).

## REQUIREMENTS ##
It depends on Static Build module.

**Requirements for user `www-data`:**
- Node
- Gatsby >= 2

## INSTALLATION ##
Run `composer require drupal/static_builder_gatsby`.

Follow the instructions available at `/admin/config/static/build`, and
create the directory structure as stated in that configuration page.

You should now have a folder
`[BASE_DIRECTORY]/gatsby/[live|preview]/.build`.

Inside that folder, add Gatsby files (you should place here `gatsby-config.js`,
`gatsby-node.js`, etc) and run `npm install` inside it, so everything needed
by Gatsby is in place.

As a best practice:
 * Ensure that running `gatsby build` inside `.build` folder works without
   errors before trying to use this module.
 * Change ownership of all files inside `.build` folder, including the
   `node_modules` folder, so they belong to `www-data` user. This usually makes
   executing Gatsby builds much faster.

## CONFIGURATION ##
There are two configuration types involved in this module.
* global configuration for the Static Build module:
  `/admin/config/static/build`
* Gatsby configuration: `/admin/config/static/build/gatsby`
