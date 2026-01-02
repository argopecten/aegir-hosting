<?php
/**
 * @file
 * Hosting service classes for the Hosting web server module.osting service classes for the Hosting web server module.
 */

class hostingService_http extends hostingService {
  public $service = 'http';
  public $ssl_enabled = FALSE;
}


class hostingService_http_public extends hostingService_http {
  protected $has_port = TRUE;

  function default_port() {
    return 80;
  }

  function update() {
    parent::update();
  }

  public function context_options($task_type, $ref_type, &$task) {
    parent::context_options($task_type, $ref_type, $task);
  }
}

class hostingService_http_apache extends hostingService_http_public {
  public $type = 'apache';
  public $name = 'Apache';

  protected $has_restart_cmd = TRUE;

  function default_restart_cmd() {
    $systemctl = NULL;
    if (is_executable('/usr/bin/systemctl')) {
      $systemctl = '/usr/bin/systemctl';
    }
    elseif (is_executable('/bin/systemctl')) {
      $systemctl = '/bin/systemctl';
    }
    if ($systemctl) {
      return "sudo $systemctl reload apache2";
    }

    return "sudo $systemctl reload apache2";
  }
}
