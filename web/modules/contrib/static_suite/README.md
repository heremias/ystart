# Static Suite

## INTRODUCTION ##
A suite of modules to decouple Drupal and turn it into a static site, built
by a Static Site Generator:

* Static Export: exports Drupal data to static files (JSON, XML, etc) using
  GraphQL, JSON:API, or any other resolver.
* Static Build: use a Static Site Generator (Gatsby, Eleventy, Hugo, etc) to
  build a static site
* Static Deploy: deploys the above site to any kind of hosting service (AWS S3,
  Netlify, etc)
* Static Preview: enables previewing content without building the whole site.
  There is also an instant preview system for Gatsby.

This suite is completely extensible, configurable and adaptable to multiple
scenarios, but it's been extensively tested with JSON files, Gatsby and AWS S3.

Documentation about each module can be found inside each one's folder.

## DISCLAIMER ##
This is not a suite of modules for beginners. To take real advantage of it,
you should have a solid foundation on Drupal, to be able to adapt it to your
needs.

On the other hand, this is a *WORK IN PROGRESS* and is subject to change.
Some coming features could break things and you should expect some current
features to be completely refactored (most of them won't break things).

While we will do our best to ensure backward compatibility, you should expect
some kind of instability while this project is on alpha state.

Static Suite is the result of several years of working with decoupled Drupal
and Gatsby, and all you can find here is the response to real needs on our
projects. Most of them are sites with thousands of pages, so expect some
features to be focused on performance and scalability and not so much on ease
of use.

## INSTALLATION ##
Run `composer require drupal/static_suite`.

## CONFIGURATION ##
Configuration available at /admin/config/static.
