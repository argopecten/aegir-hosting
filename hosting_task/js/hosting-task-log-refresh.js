/**
 * @file
 * AJAX refresh for the hosting task log on task view pages.
 *
 * Polls the server to update the task log table for active
 * (queued/processing) tasks.
 */

(function (Drupal, drupalSettings, once) {

  'use strict';

  const REFRESH_INTERVAL = 5000;

  Drupal.behaviors.hostingTaskLogRefresh = {
    attach: function (context) {
      const wrappers = once('hosting-task-log-refresh', '#hosting-task-log-wrapper', context);
      if (!wrappers.length) {
        return;
      }

      const refreshUrl = drupalSettings.hostingTaskLog?.refreshUrl;
      const taskStatus = drupalSettings.hostingTaskLog?.status;
      if (!refreshUrl) {
        return;
      }

      // Only auto-refresh for active tasks.
      if (taskStatus !== 'queued' && taskStatus !== 'processing') {
        return;
      }

      const wrapper = wrappers[0];

      const intervalId = setInterval(function () {
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
              Drupal.attachBehaviors(wrapper);
            }
          })
          .catch(function () {
            // Silently ignore refresh errors.
          });
      }, REFRESH_INTERVAL);

      wrapper._hostingLogRefreshInterval = intervalId;
    },

    detach: function (context, settings, trigger) {
      if (trigger !== 'unload') return;
      const wrapper = context.querySelector ? context.querySelector('#hosting-task-log-wrapper') : null;
      if (wrapper && wrapper._hostingLogRefreshInterval) {
        clearInterval(wrapper._hostingLogRefreshInterval);
        delete wrapper._hostingLogRefreshInterval;
      }
    }
  };

})(Drupal, drupalSettings, once);
