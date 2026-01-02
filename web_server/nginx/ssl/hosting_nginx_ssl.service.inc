<?php
/**
 * @file Define a Hosting service class for NGINX w/SSL.
 */

\Drupal::moduleHandler()->loadInclude('hosting_web_server', 'service.inc');
\Drupal::moduleHandler()->loadInclude('hosting_ssl', 'service.inc');

class hostingService_http_nginx_ssl extends hostingService_http_ssl {

  public $type = 'nginx_ssl';
  public $name = 'NGINX SSL';
  protected $has_restart_cmd = TRUE;

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
