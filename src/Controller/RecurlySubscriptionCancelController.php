<?php

/**
 * @file
 * Contains \Drupal\recurly\Controller\RecurlySubscriptionCancelController.
 */

namespace Drupal\recurly\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Recurly cancel subscription controller.
 */
class RecurlySubscriptionCancelController extends ControllerBase {

  /**
   * Cancel the specified subscription.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity whose subscription is being cancelled.
   * @param string $subscription_id
   *   The UUID of the current subscription if changing the plan on an existing
   *   subscription.
   */
  public function subscriptionCancel(EntityInterface $entity, $subscription_id) {
    // Initialize the Recurly client with the site-wide settings.
    if (!recurly_client_initialize()) {
      return ['#markup' => $this->t('Could not initialize the Recurly client.')];
    }

    $entity_type = $entity->getEntityType()->getLowercaseLabel();
    // Load the subscription.
    if ($subscription_id === 'latest') {
      $local_account = recurly_account_load([
        'entity_type' => $entity_type,
        'entity_id' => $entity->id(),
      ], TRUE);
      $subscriptions = recurly_account_get_subscriptions($local_account->account_code, 'active');
      $subscription = reset($subscriptions);
    }
    else {
      try {
        $subscription = \Recurly_Subscription::get($subscription_id);
      }
      catch (\Recurly_NotFoundError $e) {
        drupal_set_message($this->t('Subscription not found'));
        throw new NotFoundHttpException();
      }
    }

    return \Drupal::formBuilder()->getForm('Drupal\recurly\Form\RecurlySubscriptionCancelConfirmForm', $entity_type, $entity, $subscription);
  }

}
