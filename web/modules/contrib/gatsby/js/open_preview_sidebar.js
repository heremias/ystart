/**
 * @file
 * Custom code for loading the Gatsby preview in the sidebar.
 */

(function ($, Drupal) {
  'use strict';

  Drupal.behaviors.gatsby_preview_sidebar = {
    attach: function (context, settings) {
      if (context == document) {
        $("#edit-gatsby-preview").on("click", function (e) {
          e.preventDefault();

          // If the sidebar is already open, then close it.
          if ($(this).hasClass('sidebar-opened')) {
            $(this).removeClass('sidebar-opened');

            $(this).val(Drupal.t('Open Gatsby Preview'));
            $(".gatsby-iframe-sidebar").remove();
            $("body div.dialog-off-canvas-main-canvas").css("width", "100%");
          }

          // Open iframe sidebar if selected and the window is wide enough.
          else if (settings.gatsby.preview_target == 'sidebar' && window.innerWidth > 1024) {
            $(this).addClass("sidebar-opened");
            $(this).val(Drupal.t("Close Gatsby Preview"));

            // Calculate Iframe height.
            var iframeHeight = window.innerHeight - 100;
            var arrow = '<svg stroke="currentColor" fill="currentColor" stroke-width="0" viewBox="0 0 24 24" height="1em" width="1em" xmlns="http://www.w3.org/2000/svg"><path d="M12 4l-1.41 1.41L16.17 11H4v2h12.17l-5.58 5.59L12 20l8-8z"></path></svg>';

            var gatsbyIframe = '<div class="gatsby-iframe-sidebar">';
            gatsbyIframe += '<a class="gatsby-link" href="' + settings.gatsby.server_url + settings.gatsby.path + '" target="_blank">' + Drupal.t('Open in New Window ') + arrow + '</a>';
            gatsbyIframe += '<iframe width="100%" height=" ' + iframeHeight + '" class="gatsby-iframe" src="' + settings.gatsby.server_url + settings.gatsby.path + '" />';
            gatsbyIframe += '</div>';

            $('body div.dialog-off-canvas-main-canvas').css('width', '50%').css('float', 'left').after(gatsbyIframe);
          }
        });
      }
    }
  };

})(jQuery, Drupal);
