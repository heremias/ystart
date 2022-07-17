/**
 * This file is copied from docroot/core/misc/batch.js, to be able to adjust the delay value.
 * Keep it in sync with the original file whenever it changes.
 **/

(function ($, Drupal) {
  Drupal.behaviors.batch = {
    attach: function attach(context, settings) {
      var batch = settings.batch;
      var $progress = $('[data-drupal-progress]').once('batch');
      var progressBar;
      var lastProgress;

      function updateCallback(progress, status, pb) {
        if (progress === '100') {
          pb.stopMonitoring();
          window.location = "".concat(batch.uri, "&op=finished");
        } else if (lastProgress && progress && parseInt(lastProgress) > parseInt(progress)) {
          pb.stopMonitoring();
          progressBar.setProgress(100, batch.nextStepMessage);
          setTimeout(() => {
            window.location = "".concat(batch.uri, "&op=finished");
          }, 1000)
        } else if (status && status.startsWith('FAILED: ')) {
          pb.stopMonitoring();
          $progress.empty();
          $progress.prepend($('<span class="finished"></span>').html(status.replace('FAILED: ', '')));
        }
        lastProgress = progress;
      }

      function errorCallback(pb) {
        $progress.prepend($('<p class="error"></p>').html(batch.errorMessage));
        $('#wait').hide();
      }

      if ($progress.length) {
        progressBar = new Drupal.ProgressBar('updateprogress', updateCallback, 'POST', errorCallback);
        progressBar.setProgress(-1, batch.initMessage);

        var delay = 3000;
        if (typeof batch.delay !== 'undefined' && batch.delay !== '') {
          delay = batch.delay;
        }

        progressBar.startMonitoring("".concat(batch.uri, "&op=do"), delay);
        $progress.empty();
        $progress.append(progressBar.element);
      }
    }
  };
})(jQuery, Drupal);
