/**
 * @file
 * Task queue management interface with live updates using fetch() API.
 */

(function (Drupal, drupalSettings, once) {

  'use strict';

  Drupal.behaviors.taskQueueRefresh = {
    attach: function (context) {
      const tables = once('task-queue-refresh', '.task-queue-table', context);
      if (!tables.length) {
        return;
      }

      const refreshUrl = drupalSettings.taskQueue?.refreshUrl;
      if (!refreshUrl) {
        return;
      }

      setInterval(function () {
        fetch(refreshUrl, {
          method: 'GET',
          credentials: 'same-origin',
          headers: { 'Accept': 'application/json' },
        })
          .then(function (response) {
            if (!response.ok) throw new Error('Network response was not ok');
            return response.json();
          })
          .then(function (tasks) {
            updateTaskTable(tasks);
            updateStatistics(tasks);
          })
          .catch(function (err) {
            // Silent failure - log only in development.
            if (drupalSettings.path?.currentPath === 'admin/hosting/tasks/queue') {
              console.warn('Task queue refresh failed:', err.message);
            }
          });
      }, 5000);
    }
  };

  function updateTaskTable(tasks) {
    const tbody = document.querySelector('.task-queue-table tbody');
    if (!tbody) return;

    tasks.forEach(function (task) {
      const row = tbody.querySelector('tr[data-task-id="' + task.id + '"]');
      if (!row) return;

      const statusCell = row.querySelector('.task-status');
      if (statusCell) {
        statusCell.className = 'task-status task-status--' + task.status;
        statusCell.textContent = task.status;
      }

      const retryCell = row.querySelector('td:nth-child(5)');
      if (retryCell) {
        retryCell.textContent = task.retry_count + '/' + task.max_retries;
      }

      if (task.duration) {
        const durationCell = row.querySelector('td:nth-child(6)');
        if (durationCell) {
          durationCell.textContent = formatDuration(task.duration);
        }
      }
    });
  }

  function updateStatistics(tasks) {
    const stats = { queued: 0, processing: 0, failed: 0, success: 0 };

    tasks.forEach(function (task) {
      if (stats.hasOwnProperty(task.status)) {
        stats[task.status]++;
      }
    });

    Object.keys(stats).forEach(function (status) {
      const el = document.querySelector('.stat-card--' + status + ' .stat-count');
      if (el) el.textContent = stats[status];
    });
  }

  function formatDuration(seconds) {
    if (seconds < 60) {
      return seconds + ' sec';
    }
    const minutes = Math.floor(seconds / 60);
    const secs = seconds % 60;
    return minutes + ' min ' + secs + ' sec';
  }

})(Drupal, drupalSettings, once);
