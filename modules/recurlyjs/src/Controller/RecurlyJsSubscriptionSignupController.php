<?php

/**
 * @file
 * Contains \Drupal\recurlyjs\Controller\RecurlyJsSubscriptionSignupController.
 */

namespace Drupal\recurlyjs\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Returns responses for Recurly Subscription List.
 */
class RecurlyJsSubscriptionSignupController extends ControllerBase {

  /**
   * Controller callback to trigger a user subscription.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   * @param string $plan_code
   *   A Recurly plan code.
   * @param string $currency
   *   The designated currency to use when subscribing.
   *
   * @return array
   *   A Drupal render array.
   */
  public function subscribe(EntityInterface $entity, $plan_code, $currency = NULL) {
    // Initialize the Recurly client with the site-wide settings.
    if (!recurly_client_initialize()) {
      return ['#markup' => $this->t('Could not initialize the Recurly client.')];
    }

    $entity_type = $entity->getEntityType()->getLowercaseLabel();
    // Ensure the account does not already have this exact same plan. Recurly
    // does not support a single account having multiple of the same plan.
    $local_account = recurly_account_load(['entity_type' => $entity_type, 'entity_id' => $entity->id()], TRUE);
    if ($local_account) {
      $current_subscriptions = recurly_account_get_subscriptions($local_account->account_code, 'active');
      // If the account is only allowed one subscription total, they shouldn't
      // ever see this signup page.
      if ((\Drupal::config('recurly.settings')->get('recurly_subscription_max') ?: '1') === '1' && count($current_subscriptions) && empty($_POST)) {
        $current_subscription = reset($current_subscriptions);
        drupal_set_message($this->t('This account already has a @plan plan!', ['@plan' => $current_subscription->plan->name]));
        if ($url = recurly_url('select_plan', array('entity_type' => $entity_type, 'entity' => $entity))) {
          return $this->redirect($url->getRouteName(), $url->getRouteParameters());
        }
      }
      // Otherwise check if they already have one of this same plan.
      foreach ($current_subscriptions as $current_subscription) {
        if ($current_subscription->plan->plan_code === $plan_code && empty($_POST)) {
          drupal_set_message($this->t('This account already has a @plan plan!', ['@plan' => $current_subscription->plan->name]));
          if ($url = recurly_url('subscribe', [
            'entity_type' => $entity_type,
            'entity' => $entity,
            'plan_code' => $plan_code,
          ])) {
            return $this->redirect($url->getRouteName(), $url->getRouteParameters());
          }
        }
      }
    }
    // Although this controller contains little else besides the subscription
    // form, it's a separate class because it's highly likely to need theming.
    $form = \Drupal::formBuilder()->getForm('Drupal\recurlyjs\Form\RecurlyJsSubscribeForm', $entity_type, $entity, $plan_code, $currency);
    try {
      $plan = \Recurly_Plan::get($plan_code);
    }
    catch (\Recurly_NotFoundError $e) {
      throw new NotFoundHttpException();
    }

    return [
      '#theme' => [
        'recurlyjs_subscribe_page',
      ],
      '#form' => $form,
    ];
  }

}
