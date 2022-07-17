# Gatsby Webform Backend

Drupal module for [Gatsby Drupal Webform](https://github.com/oikeuttaelaimille/gatsby-drupal-webform) project. Goal of this project is to have a react component that generates [bootstrap like](https://getbootstrap.com/docs/4.0/components/forms/) HTML from [webform](https://www.drupal.org/project/webform) YAML configuration.

This module is meant to be used with decoupled Drupal sites that are built with JSON:API and React, for example [Gatsby](https://www.gatsbyjs.org/). This module is required by Gatsby Drupal Webform frontend component.
Installation

 1. Enable module.
 2. Enable REST resource "Webform Submit".
 3. Give `access any webform configuration` permission to user accesssing jsonapi.
 4. If your frontend is hosted on a different domain make sure browser has cross origin access to REST resource.

See [readme.md](https://github.com/oikeuttaelaimille/gatsby-drupal-webform/blob/master/README.md) for react component documentation.

Features
 * jsonapi_extras field enhancer that translates webform yaml jsonapi output to a format that Gatsby Drupal Webform frontend component understands.
 * rest resource that can be used to submit webforms from frontend.
