/**
 * @file
 * Task queue management interface with live updates.
 */

(function ($, Drupal, drupalSettings) {

  'use strict';

  /**
   * Auto-refresh task queue.
   */
  Drupal.behaviors.taskQueueRefresh = {
    attach: function (context, settings) {
      const $table = $('.task-queue-table', context).once('task-queue-refresh');
      if (!$table.length) {
        return;
      }

      const refreshUrl = drupalSettings.taskQueue?.refreshUrl;
      if (!refreshUrl) {
        return;
      }

      // Refresh every 5 seconds.
      setInterval(function () {
        $.ajax({
          url: refreshUrl,
          method: 'GET',
          dataType: 'json',
          success: function (tasks) {
            updateTaskTable(tasks);
            updateStatistics(tasks);
          },
          error: function () {
            console.error('Failed to refresh task queue');
          }
        });
      }, 5000);
    }
  };

  /**
   * Update task table with fresh data.
   */
  function updateTaskTable(tasks) {
    const $tbody = $('.task-queue-table tbody');
    
    tasks.forEach(function (task) {
      const $row = $tbody.find('tr[data-task-id="' + task.id + '"]');
      
      if ($row.length) {
        // Update existing row.
        $row.find('.task-status')
          .removeClass()
          .addClass('task-status task-status--' + task.status)
          .text(task.status);
        
        $row.find('td:nth-child(5)').text(task.retry_count + '/' + task.max_retries);
        
        if (task.duration) {
          $row.find('td:nth-child(6)').text(formatDuration(task.duration));
        }
      }
    });
  }

  /**
   * Update statistics cards.
   */
  function updateStatistics(tasks) {
    const stats = {
      queued: 0,
      processing: 0,
      failed: 0,
      success: 0
    };

    tasks.forEach(function (task) {
      if (stats.hasOwnProperty(task.status)) {
        stats[task.status]++;
      }
    });

    Object.keys(stats).forEach(function (status) {
      $('.stat-card--' + status + ' .stat-count').text(stats[status]);
    });
  }

  /**
   * Format duration in seconds to human readable.
   */
  function formatDuration(seconds) {
    if (seconds < 60) {
      return seconds + ' sec';
    }
    const minutes = Math.floor(seconds / 60);
    const secs = seconds % 60;
    return minutes + ' min ' + secs + ' sec';
  }

})(jQuery, Drupal, drupalSettings);
