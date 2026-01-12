<?php

/**
 * @file
 * Hooks for the Hosting module.
 */

/**
 * Define hosting features.
 *
 * @return array
 *   Feature definitions keyed by machine name.
 *   - title
 *   - description
 *   - module
 *   - required
 *   - dependencies (array of module names)
 *   - role_permissions (role label => permissions array)
 */
function hook_hosting_feature() {
  return [
    'example' => [
      'title' => t('Example feature'),
      'description' => t('Example feature definition.'),
      'module' => 'hosting_example',
      'required' => FALSE,
      'dependencies' => ['hosting_task'],
      'role_permissions' => [
        'aegir administrator' => [
          'administer hosting',
        ],
      ],
    ],
  ];
}

/**
 * Provide queue definitions for Hosting.
 *
 * @return array
 *   Queue definitions keyed by queue machine name.
 *   - label
 *   - description
 *   - type (serial|batch|spread)
 *   - frequency (seconds)
 *   - items (for serial)
 *   - queue_id (QueueWorker plugin ID)
 *   - max_threads, min_threads, threshold (batch)
 */
function hook_hosting_queue_info() {
  return [
    'tasks' => [
      'label' => t('Tasks'),
      'description' => t('Execute provisioning tasks.'),
      'type' => 'serial',
      'frequency' => 300,
      'items' => 5,
      'queue_id' => 'hosting_task',
    ],
  ];
}
