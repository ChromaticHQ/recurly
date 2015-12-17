<?php

/**
 * @file
 * Contains \Drupal\recurly\Controller\RecurlySubscriptionListController.
 */

namespace Drupal\recurly\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Component\Utility\Xss;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Returns responses for Recurly Subscription List.
 */
class RecurlySubscriptionListController extends ControllerBase {

  /**
   * Route title callback.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity whose subscriptons should be listed.
   *
   * @return array
   *   Recurly subscription details or a no-results message as a render array.
   */
  public function subscriptionList(EntityInterface $entity) {
    $subscriptions = [];
    // Initialize the Recurly client with the site-wide settings.
    if (!recurly_client_initialize()) {
      return t('Could not initialize the Recurly client.');
    }
    // Load the account information. This should already be cached by the access
    // check to this page by recurly_subscription_page_access().
    $entity_type = $entity->getEntityType()->getLowercaseLabel();
    $account = recurly_account_load(['entity_type' => $entity_type, 'entity_id' => $entity->id()]);
    // If the user does not have an account yet, send them to the signup page.
    if (empty($account)) {
      if ($url = recurly_url('select_plan', array('entity_type' => $entity_type, 'entity' => $entity))) {
        return $this->redirect($url->getRouteName(), $url->getRouteParameters());
      }
      else {
        throw new NotFoundHttpException();
      }
    }
  }
}
