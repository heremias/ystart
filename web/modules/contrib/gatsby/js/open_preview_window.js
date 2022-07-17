/**
 * @file
 * Custom code for loading the Gatsby preview in a new window.
 */

(function ($, Drupal) {
  'use strict';

  Drupal.behaviors.gatsby_preview_window = {
    attach: function (context, settings) {
      if (context == document) {
        $("#edit-gatsby-preview").on("click", function (e) {
          e.preventDefault();

          // Build a string to use as the window name so that each environment
          // can have unique requests but still provide a way of a single edit
          // form to reload its preview in the same window. This will result
          // in a window name in the format "example-com--node--123".
          var window_name = window.location.host.replaceAll('.', '-') + '--' + settings.gatsby.entity_type + '--' + settings.gatsby.entity_id;

          // Open the Content Sync URL.
          if (settings.gatsby.contentsync_url != '') {
            window.open(settings.gatsby.contentsync_url, window_name);
          }

          // Open the regular Gatsby page.
          else {
            // Get the alias on page load, not the alias that might be edited
            // and thus trigger a 404.
            window.open(settings.gatsby.server_url + settings.gatsby.path, window_name);
          }
        });
      }
    }
  };

})(jQuery, Drupal);
