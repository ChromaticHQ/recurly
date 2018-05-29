<?php

namespace Drupal\recurly_hosted\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Redirects to hosted pages.
 */
class RecurlyHostedAccountRedirectController extends ControllerBase {

  /**
   * Redirect to hosted pages.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   Contains information about the route and the entity being acted on.
   */
  public function redirectToAccountManagement(RouteMatchInterface $route_match) {
    $entity_type_id = $this->config('recurly.settings')->get('recurly_entity_type');
    $entity = $route_match->getParameter($entity_type_id);
    if ($recurly_account = recurly_account_load(['entity_type' => $entity_type_id, 'entity_id' => $entity->id()])) {
      if ($url = recurly_hosted_account_manage_url($recurly_account, TRUE)) {
        return new TrustedRedirectResponse($url->toString());
      }
    }
    throw new NotFoundHttpException();
  }

}
