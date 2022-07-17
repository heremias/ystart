/**
 * This file is copied from docroot/core/misc/batch.js
 * Keep it in sync with the original file whenever it changes.
 */
(function ($, Drupal) {
  Drupal.behaviors.batch = {
    attach: function attach(context, settings) {

      $('.toolbar-progress-bar').each(function (index) {

        const $uri = $(this).attr('data-uri');
        const $progress = $(this).find('[data-drupal-progress]').once('batch');

        toolbar_progress_bar($, settings, $progress, $uri)
      });
    }
  };
})(jQuery, Drupal);

function toolbar_progress_bar($, settings, $progress, $uri) {
  let batch = settings.batch;
  let progressBar;

  function updateCallback(progress, status, pb) {
    $progress.closest('.toolbar-progress-bar').show();
    if (status && status.startsWith('FAILED: ')) {
      pb.stopMonitoring();
      $progress.empty();
      $progress.prepend($('<span class="finished"></span>').html(status.replace('FAILED: ', '')));
    } else if (progress === '100') {
      pb.stopMonitoring();
      $progress.empty();
      $progress.prepend($('<span class="finished"></span>').html(status));
    }
  }

  // Hide errors on toolbar.
  function errorCallback(pb) {
    $progress.closest('.toolbar-progress-bar').hide();
  }

  if ($progress.length) {
    progressBar = new Drupal.ProgressBar('updateprogress', updateCallback, 'POST', errorCallback);

    let delay = 3000;
    if (typeof batch.delay !== 'undefined' && batch.delay !== '') {
      delay = batch.delay;
    }

    progressBar.startMonitoring("".concat($uri, "&op=do"), delay);
    $progress.empty();
    $progress.append(progressBar.element);
  }
}
