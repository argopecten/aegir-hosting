/**
 * @file
 * Platform form behaviors.
 */

(function ($, Drupal) {
  'use strict';

  /**
   * Auto-populate directory name from platform label.
   */
  Drupal.behaviors.platformFormDirectoryName = {
    attach: function (context, settings) {
      var $label = $('input[name="label[0][value]"]', context).once('platform-directory-name');
      var $directoryName = $('input[name="directory_name"]', context);
      
      if ($label.length && $directoryName.length) {
        // Flag to track if user has manually edited directory name
        var manuallyEdited = false;
        
        $directoryName.on('input', function() {
          if ($(this).val() !== '') {
            manuallyEdited = true;
          }
        });
        
        $label.on('input', function() {
          // Only auto-populate if user hasn't manually edited
          if (!manuallyEdited || $directoryName.val() === '') {
            var sanitized = sanitizeDirectoryName($(this).val());
            $directoryName.val(sanitized);
          }
        });
        
        // Initialize with current label value if directory name is empty
        if ($directoryName.val() === '' && $label.val() !== '') {
          $directoryName.val(sanitizeDirectoryName($label.val()));
        }
      }
      
      /**
       * Sanitize a string for use as a directory name.
       */
      function sanitizeDirectoryName(name) {
        return name
          .toLowerCase()
          .replace(/[^a-z0-9_-]/gi, '_')
          .replace(/_+/g, '_')
          .replace(/^_+|_+$/g, '');
      }
    }
  };

})(jQuery, Drupal);
