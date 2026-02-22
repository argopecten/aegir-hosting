/**
 * @file
 * AJAX refresh for the hosting task table on entity view pages.
 *
 * Polls the server to update the task action table (run/view buttons)
 * and status indicators without full page reload.
 */

(function (Drupal, drupalSettings, once) {

  'use strict';

  /**
   * Refresh interval in milliseconds.
   */
  const REFRESH_INTERVAL = 10000;

  Drupal.behaviors.hostingTaskTableRefresh = {
    attach: function (context) {
      const wrappers = once('hosting-task-table-refresh', '#hosting-task-list', context);
      if (!wrappers.length) {
        return;
      }

      const refreshUrl = drupalSettings.hostingTaskTable?.refreshUrl;
      if (!refreshUrl) {
        return;
      }

      const wrapper = wrappers[0];
      let intervalId = null;

      // Start polling.
      intervalId = setInterval(function () {
        fetch(refreshUrl, {
          method: 'GET',
          credentials: 'same-origin',
          headers: {
            'Accept': 'text/html',
            'X-Requested-With': 'XMLHttpRequest',
          },
        })
          .then(function (response) {
            if (!response.ok) throw new Error('Refresh failed');
            return response.text();
          })
          .then(function (html) {
            if (html.trim()) {
              wrapper.innerHTML = html;
              // Re-attach Drupal behaviors to the new content.
              Drupal.attachBehaviors(wrapper);
            }
          })
          .catch(function () {
            // Silently ignore refresh errors.
          });
      }, REFRESH_INTERVAL);

      // Clean up on detach.
      wrapper._hostingRefreshInterval = intervalId;
    },

    detach: function (context, settings, trigger) {
      if (trigger !== 'unload') return;
      const wrapper = context.querySelector ? context.querySelector('#hosting-task-list') : null;
      if (wrapper && wrapper._hostingRefreshInterval) {
        clearInterval(wrapper._hostingRefreshInterval);
        delete wrapper._hostingRefreshInterval;
      }
    }
  };

})(Drupal, drupalSettings, once);
