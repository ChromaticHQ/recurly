<?php

/**
 * @file
 * Contains \Drupal\recurly\Controller\RecurlySubscriptionSelectPlanController.
 */

namespace Drupal\recurly\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityInterface;

/**
 * Recurly select plan controller.
 */
class RecurlySubscriptionSelectPlanController extends ControllerBase {

  const SELECT_PLAN_MODE_SIGNUP = 'signup';

  const SELECT_PLAN_MODE_CHANGE = 'change';

  /**
   * Show a list of available plans to which a user may subscribe.
   *
   * This method is used both for new subscriptions and for updating existing
   * subscriptions.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity whose subscription is being changed.
   * @param string $currency
   *   If this is a new subscription, the currency to be used.
   * @param string $subscription_id
   *   The UUID of the current subscription if changing the plan on an existing
   *   subscription.
   */
  public function planSelect(EntityInterface $entity, $currency = NULL, $subscription_id = NULL) {
    $entity_type = $entity->getEntityType()->getLowercaseLabel();
    $content = $entity ? $entity->label() : t('No corresponding entity loaded!');

    // Initialize the Recurly client with the site-wide settings.
    if (!recurly_client_initialize()) {
      return t('Could not initialize the Recurly client.');
    }

    // If loading an existing subscription.
    // @TODO: Test existing sub.
    if ($subscription_id) {
      if ($subscription_id === 'latest') {
        $local_account = recurly_account_load(['entity_type' => $entity_type, 'entity_id' => $entity->id()], TRUE);
        $subscriptions = recurly_account_get_subscriptions($local_account->account_code, 'active');
        $subscription = reset($subscriptions);
        $subscription_id = $subscription->uuid;
        $currency = $subscription->plan->currency;
        $mode = self::SELECT_PLAN_MODE_CHANGE;
      }
      else {
        try {
          $subscription = Recurly_Subscription::get($subscription_id);
          $subscriptions[$subscription->uuid] = $subscription;
          $currency = $subscription->plan->currency;
          $mode = self::SELECT_PLAN_MODE_CHANGE;
        }
        catch (Recurly_NotFoundError $e) {
          throw new NotFoundHttpException(t('Subscription not found'));
        }
      }
    }
    // If signing up to a new subscription, ensure the user doesn't have a plan.
    else {
      $subscriptions = [];
      $currency = isset($currency) ? $currency : \Drupal::config('recurly.settings')->get('recurly_default_currency');
      $mode = self::SELECT_PLAN_MODE_SIGNUP;
      $entity_type = $entity->getEntityType()->getLowercaseLabel();
      $account = recurly_account_load(['entity_type' => $entity_type, 'entity_id' => $entity->id()]);
      if ($account) {
        $subscriptions = recurly_account_get_subscriptions($account->account_code, 'active');
      }
    }

    // Make the list of subscriptions based on plan keys, rather than uuid.
    $plan_subscriptions = [];
    foreach ($subscriptions as $subscription) {
      $plan_subscriptions[$subscription->plan->plan_code] = $subscription;
    }

    $all_plans = recurly_subscription_plans();
    $enabled_plan_keys = \Drupal::config('recurly.settings')->get('recurly_subscription_plans') ?: [];
    $enabled_plans = [];
    foreach ($enabled_plan_keys as $plan_code => $enabled) {
      foreach ($all_plans as $plan) {
        if ($enabled && $plan_code == $plan->plan_code) {
          $enabled_plans[$plan_code] = $plan;
        }
      }
    }

    return [
      '#theme' => [
        'recurly_subscription_plan_select__' . $mode,
        'recurly_subscription_plan_select',
      ],

      '#plans' => $enabled_plans,
      '#entity_type' => $entity_type,
      '#entity' => $entity,
      '#currency' => $currency,
      '#mode' => $mode,
      '#subscriptions' => $plan_subscriptions,
      '#subscription_id' => $subscription_id,
    ];
  }

}
