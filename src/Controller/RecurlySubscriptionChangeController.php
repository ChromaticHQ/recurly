<?php

/**
 * @file
 * Contains \Drupal\recurly\Controller\RecurlySubscriptionChangeController.
 */

namespace Drupal\recurly\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Recurly change subscription controller.
 */
class RecurlySubscriptionChangeController extends ControllerBase {

  /**
   * Change the existing to the specified subscription
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity whose subscription is being changed.
   * @param string $subscription_id
   *   The UUID of the current subscription if changing the plan on an existing
   *   subscription.
   * @param string $new_plan_code
   *   The plan code for the plan the user is changing to.
   */
  public function changePlan(EntityInterface $entity, $subscription_id, $new_plan_code) {
    // Initialize the Recurly client with the site-wide settings.
    if (!recurly_client_initialize()) {
      return t('Could not initialize the Recurly client.');
    }

    // Load the subscription.
    try {
      $subscription = \Recurly_Subscription::get($subscription_id);
    }
    catch (\Recurly_NotFoundError $e) {
      drupal_set_message(t('Subscription not found.'));
      return MENU_NOT_FOUND;
    }

    // Load the old plan.
    try {
      $previous_plan = \Recurly_Plan::get($subscription->plan->plan_code);
    }
    catch (\Recurly_NotFoundError $e) {
      drupal_set_message(t('Plan code "@plan" not found.', ['@plan' => $subscription->plan->plan_code]));
      return MENU_NOT_FOUND;
    }

    // Load the new plan.
    try {
      $new_plan = \Recurly_Plan::get($new_plan_code);
    }
    catch (\Recurly_NotFoundError $e) {
      drupal_set_message(t('Plan code "@plan" not found.', ['@plan' => $new_plan_code]));
      return MENU_NOT_FOUND;
    }

    $entity_type = $entity->getEntityType()->getLowercaseLabel();
    return \Drupal::formBuilder()->getForm('Drupal\recurly\Form\RecurlySubscriptionChangeConfirmForm', $entity_type, $entity, $subscription, $previous_plan, $new_plan);
  }

}
