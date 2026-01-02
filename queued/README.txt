Hosting queue daemon
====================

Simple Drupal module intended to make it easy to run the Aegir tasks
queue with near-instant execution times. The daemon is designed to run
standalone, and started through a process manager of your choice.
No init scripts or packaged service definitions are shipped.

Note that before the service is setup and the daemon can be started,
it needs to be enabled as a module in the frontend.

Install this module in your main hostmaster (Aegir) site. You can
enable the feature from: `admin/hosting`. This will disable your
hosting tasks queue for you, ready for you to enable the daemon.

The daemon logs some of its activities to the Drupal watchdog.

Running as a service
--------------------

Use your process manager to run `drush hosting-queued` under the Aegir user.
No packaged init scripts or supervisor configs are included.

Troubleshooting
---------------

Try to run `drush hosting-queued` from the command line as the Aegir user. If
that works, the issue is likely with your process manager configuration.

Look into the Drupal watchdog to see when the daemon has been started
or was restarted. The settings page should also tell you the last time
the daemon was started.
