# Gatsby Integration

This project enhances the experience of your Drupal content editors for
[Gatsby](https://www.gatsbyjs.com) sites using Drupal as a data source. This
module provides Gatsby live preview capabilities using your [Gatsby
Cloud](https://www.gatsbyjs.com/products/cloud/) Preview account or on your
locally running [Gatsby development server](https://github.com/gatsbyjs/gatsby).

Live Preview will work with JSON:API (using
[gatsby-source-drupal](https://github.com/gatsbyjs/gatsby/tree/master/packages/gatsby-source-drupal))
or the GraphQL module (using
[gatsby-source-graphql](https://github.com/gatsbyjs/gatsby/tree/master/packages/gatsby-source-graphql)).
Incremental builds currenly only works with JSON:API, however, you can still
configure the module to trigger a full build if you are using GraphQL.

## Requirements

This module requires no modules outside of Drupal core.

## Included submodules

The Gatsby module comes included with multiple submodules. Please read below
for an overview of each submodule. Refer to the modules README files inside the
modules/ folder.

* Gatsby JSON:API Instant Preview & Incremental Builds (gatsby_instantpreview):
  this module allows a faster preview experience and incremental build support
  for Gatsby sites that use gatsby-source-drupal and JSON:API.

* Gatsby JSON:API Extras (gatsby_extras): provides additional JSON:API features
  such as improved menu link support.

* Gatsby Endpoints (gatsby_endpoints): The Gatsby Endpoints module was
  previously available in the dev version of this module. However, it has
  been moved to a separate project on Drupal.org. This module makes it
  possible to source multiples Gatsby sites from a single Drupal instance.
  See https://www.drupal.org/project/gatsby_endpoints for more information.

## Installation

1. Download and install the module as you would any other Drupal 9 module.
   Using composer is the preferred method. Visit
   https://www.drupal.org/node/1897420 for further information.
2. Make sure to turn on the Gatsby Refresh endpoint by enabling the environment
   variable 'ENABLE_GATSBY_REFRESH_ENDPOINT' in your Gatsby environment file as
   documented in https://www.gatsbyjs.org/docs/environment-variables/
3. It's easiest to use this by signing up for Gatsby Cloud at
   https://www.gatsbyjs.com/. There is a free tier that will work for most
   development and small production sites. This is needed for incremental builds
   to work.
4. You can also configure this to run against a local Gatsby development
   server for live preview. You need to make sure your Drupal site can
   communicate with your Gatsby development server over port 8000. Using
   a tool such as ngrok can be helpful for testing this locally.
5. Install the gatsby-source-drupal plugin on your Gatsby site if using JSON:API
   or gatsby-source-graphql if you are using the Drupal GraphQL module. There
   are no additional configuration options needed for the plugin to work
   (besides enabling the __refresh endpoing as documented above). However,
   you can add the optional secret key (JSON:API only) to match your Drupal
   configuration's secret key.

        module.exports = {
          plugins: [
            {
              resolve: `gatsby-source-drupal`,
              options: {
                baseUrl: `...`,
                secret: `any-value-here`
              }
            }
          ]
        };


6. Enable the Gatsby module.
7. Navigate to the configuration page for the Gatsby Drupal module.
8. Copy the URL to your Gatsby preview server (either Gatsby cloud or a locally
   running instance). Once you have that, the Gatsby site is set up to receive
   updates.
9. Add an optional secret key to match the configuration added to your
   gatsby-config.js file as documented above.
10. Optionally add the callback webhook from Gatsby Cloud to trigger your
    incremental builds (JSON:API only). You can also check the box which will
    only trigger incremental builds when you publish content. You can also enter
    a build callback URL in this box (which can be used to trigger build
    services such as Netlify).
11. If you are updating the Gatsby Drupal module and are still using an old
    version of gatsby-source-drupal, you can select to use the legacy callback.
    Note: this will eventually be removed and it's recommended to upgrade your
    gatsby-source-drupal plugin on your Gatsby site.
12. Select the entity types that should be sent to the Gatsby Preview server.
    At minimum you typically will need to check `Content` but may need other
    entity types such as Files, Media, Paragraphs, etc.
13. Save the configuration form.
14. If you want to enable the Gatsby Preview button, go to the Content Type
    edit page and check the boxes for the features you would like to enable for
    that specific content type.
15. To use the iframe preview, customize the display settings for the content
    type's view mode.

Now you're all set up to use preview and incremental builds! Make a change to
your content, press save, and watch as your Gatsby Preview site magically
updates and your incremental builds are triggered in Gatsby Cloud!

## Configuration

@todo

## Fastbuilds

Speeds up build times for gatsby develop and build commands by only downloading
content that has changed since the last build.

Gatsby Fastbuilds logs entity changes on your Drupal site and allows Gatsby to
only pull content that has changed since the last sync. This can dramatically
speed up build times for larger sites and can help speed up the development
process as well.

### How to use

First, enable the `fastBuilds` option in your gatsby-config.js file as documented
in the gatsby-source-drupal Gatsby plugin.

Once configured, Gatsby Fastbuilds will work in the background without any
changes to your development or build workflow. If you want to run a full build
you will need to run `gatsby clean` before your develop or build command.

### Configuration Options

There are two admin pages for the Gatsby Fastbuilds module. The first is the
log of all the entity changes. The JSON for any entity changes are stored and
then synced to the Gatsby site (running gatsby-source-drupal) based on a cached
timestamp. The entites that are logged are the ones selected on the main Gatsby
module admin page (at the bottom of the form).

The second admin page is the configuration form. The first option allwos you to
only sync published content changes. This is recommended if you are using
Fastbuilds for your production builds (to prevent unpublished content from
syncing to your live Gatsby site). You may want this to be unchecked in
development so you can see unpublished content on your Gatsby site.

The last checkbox on the configuration page allows you to specify how long to
keep log entries around. If a Gatsby site tries to sync and it's timestamp is
older than the last log entry, it will perform a full rebuild of the Gatsby
site.

### Security

This supports the following security mechanisms:
* HTTP Basic Auth
* Cookies
* Key Auth module - https://www.drupal.org/project/key_auth

## Drush commands

The following Drush commands are available:

* drush gatsby:logs:purge
  This purges the `gatsby_log_entity` table - all records are deleted.

## Troubleshooting

@todo


## Known Issues

- If you use the iframe preview on a content type it may cause an issue with
  BigPipe loading certain parts of your page. For now you can get around this
  issue by disabling BigPipe or not using the iframe preview.

## Support

The best way to get support is to join the #Gatsby channel in [Drupal
Slack](https://www.drupal.org/slack). You can also use the [issue
queue](https://www.drupal.org/project/issues/gatsby).

## Maintainers

Current maintainers:

 * [Damien McKenna (DamienMcKenna)](https://www.drupal.org/u/damienmckenna)
 * [Gabriel Martinez
   (gdmartinezsandino)](https://www.drupal.org/u/gdmartinezsandino)
 * [Ian Sholtys (iansholtys)](https://www.drupal.org/u/iansholtys)
 * [Jay Callicott (drupalninja99)](https://www.drupal.org/u/drupalninja99)
 * [John Entwistle (johnnybgoode)](https://www.drupal.org/u/johnnybgoode)
 * [Lee Rowlands (larowlan)](https://www.drupal.org/u/larowlan)
 * [Matt Davis (mrjmd)](https://www.drupal.org/u/mrjmd)
 * [Shane Thomas (codekarate)](https://www.drupal.org/u/codekarate)
