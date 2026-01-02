<?php

namespace Drupal\hosting\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Drupal\Core\Url;

class HostingController extends ControllerBase {
  /**
   * Normalize legacy callbacks into render arrays or responses.
   *
   * @param mixed $result
   *   Callback return value.
   *
   * @return array|\Symfony\Component\HttpFoundation\Response
   *   A render array or response.
   */
  protected function normalizeResult($result) {
    if (is_array($result) || $result instanceof Response) {
      return $result;
    }

    return array('#markup' => (string) $result);
  }

  /**
   * Render disabled site page.
   */
  public function disabled() {
    return $this->normalizeResult(hosting_disabled_site());
  }

  /**
   * Render maintenance page.
   */
  public function maintenance() {
    return $this->normalizeResult(hosting_site_maintenance());
  }

  /**
   * Features admin form.
   */
  public function features() {
    return \Drupal::formBuilder()->getForm(\Drupal\hosting\Form\HostingFeaturesForm::class);
  }

  /**
   * Queues configuration form.
   */
  public function queuesConfig() {
    return $this->formBuilder()->getForm('hosting_queues_configure');
  }

  /**
   * Hosting settings form.
   */
  public function settings() {
    return $this->formBuilder()->getForm('hosting_settings');
  }

  /**
   * Queues overview.
   */
  public function queues($key = '') {
    return $this->normalizeResult(hosting_queues($key));
  }

  /**
   * Redirect to the hostmaster UI.
   */
  public function hostmasterRedirect() {
    return new RedirectResponse(Url::fromUserInput('/hosting/c/hostmaster')->toString());
  }

  /**
   * Access check for hostmaster shortcut.
   */
  public function hostmasterAccess(AccountInterface $account) {
    $hostmaster_id = hosting_get_hostmaster_site_id();
    if (!$hostmaster_id) {
      $hostmaster_id = hosting_context_entity_id('hostmaster');
    }
    if (!$hostmaster_id) {
      return AccessResult::forbidden();
    }
    if (!$this->entityTypeManager()->hasDefinition('hosting_site')) {
      return AccessResult::forbidden();
    }
    $hostmaster = $this->entityTypeManager()->getStorage('hosting_site')->load($hostmaster_id);
    return AccessResult::allowedIf($hostmaster ? $hostmaster->access('view', $account) : FALSE);
  }

  /**
   * Render a page inside the hosting JS wrapper.
   */
  public function jsPage($path = '') {
    $path = '/' . ltrim($path, '/');
    $request = Request::create($path);
    return $this->container->get('http_kernel')->handle($request, HttpKernelInterface::SUB_REQUEST);
  }

}
