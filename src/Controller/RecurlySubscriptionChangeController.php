<?php

/**
 * @file
 * Contains \Drupal\recurly\Controller\RecurlySubscriptionChangeController.
 */

namespace Drupal\recurly\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Recurly change subscription controller.
 */
class RecurlySubscriptionChangeController extends ControllerBase {

  /**
   * Change the existing to the specified subscription.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   A RouteMatch object.
   *   Contains information about the route and the entity being acted on.
   *
   * @return mixed
   *   Returns \Drupal\Core\Form\FormBuilderInterface or a string.
   */
  public function changePlan(RouteMatchInterface $route_match) {
    $entity_type_id = \Drupal::config('recurly.settings')->get('recurly_entity_type') ?: 'user';
    $entity = $route_match->getParameter($entity_type_id);
    $subscription_id = $route_match->getParameter('subscription_id');
    $new_plan_code = $route_match->getParameter('new_plan_code');
    // Initialize the Recurly client with the site-wide settings.
    if (!recurly_client_initialize()) {
      return ['#markup' => $this->t('Could not initialize the Recurly client.')];
    }

    // Load the subscription.
    try {
      $subscription = \Recurly_Subscription::get($subscription_id);
    }
    catch (\Recurly_NotFoundError $e) {
      drupal_set_message($this->t('Subscription not found.'));
      return MENU_NOT_FOUND;
    }

    // Load the old plan.
    try {
      $previous_plan = \Recurly_Plan::get($subscription->plan->plan_code);
    }
    catch (\Recurly_NotFoundError $e) {
      drupal_set_message($this->t('Plan code "@plan" not found.', ['@plan' => $subscription->plan->plan_code]));
      return MENU_NOT_FOUND;
    }

    // Load the new plan.
    try {
      $new_plan = \Recurly_Plan::get($new_plan_code);
    }
    catch (\Recurly_NotFoundError $e) {
      drupal_set_message($this->t('Plan code "@plan" not found.', ['@plan' => $new_plan_code]));
      return MENU_NOT_FOUND;
    }

    $entity_type = $entity->getEntityType()->getLowercaseLabel();
    return \Drupal::formBuilder()->getForm('Drupal\recurly\Form\RecurlySubscriptionChangeConfirmForm', $entity_type, $entity, $subscription, $previous_plan, $new_plan);
  }

}
