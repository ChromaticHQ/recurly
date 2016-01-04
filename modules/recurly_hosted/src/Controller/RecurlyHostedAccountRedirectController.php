<?php

/**
 * @file
 * Contains \Drupal\recurly_hosted\Controller\RecurlyHostedAccountRedirectController.
 */

namespace Drupal\recurly_hosted\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Redirects to hosted pages.
 */
class RecurlyHostedAccountRedirectController extends ControllerBase {

  /**
   * Redirect to hosted pages.
   *
   * @param EntityInterface $entity
   *   The entity associated with Recurly subscriptions; most typically a user.
   */
  public function redirectToAccountManagement(EntityInterface $entity) {
    $entity_type = $entity->getEntityType()->getLowercaseLabel();
    if ($recurly_account = recurly_account_load(['entity_type' => $entity_type, 'entity_id' => $entity->id()])) {
      if ($url = recurly_hosted_account_manage_url($recurly_account, TRUE)) {
        return new \Drupal\Core\Routing\TrustedRedirectResponse($url);
      }
    }
    throw new NotFoundHttpException();
  }

}
