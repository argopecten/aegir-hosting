<?php
/**
 * @file
 * Define a Hosting service class for NGINX.
 */

\Drupal::moduleHandler()->loadInclude('hosting_web_server', 'service.inc');

class hostingService_http_nginx extends hostingService_http_public {
  public $type = 'nginx';
  public $name = 'NGINX';
  public $has_restart_cmd = TRUE;

  function default_restart_cmd() {
    $systemctl = NULL;
    if (is_executable('/usr/bin/systemctl')) {
      $systemctl = '/usr/bin/systemctl';
    }
    elseif (is_executable('/bin/systemctl')) {
      $systemctl = '/bin/systemctl';
    }
    return "sudo $systemctl reload nginx";
  }
}
