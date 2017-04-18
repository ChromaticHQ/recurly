<?php

namespace Drupal\recurly\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   A RouteMatch object.
   *   Contains the route and the entity subscription is being changed.
   * @param string $currency
   *   If this is a new subscription, the currency to be used.
   * @param string $subscription_id
   *   The UUID of the current subscription if changing the plan on an existing
   *   subscription.
   */
  public function planSelect(RouteMatchInterface $route_match, $currency = NULL, $subscription_id = NULL) {
    $entity_type_id = $this->config('recurly.settings')->get('recurly_entity_type');

    // Redirect authenticated users to the authenticated signup page if they're
    // on the unauthenticated one.
    if (\Drupal::currentUser()->isAuthenticated() && !$route_match->getParameters()->count()) {
      $authenticated_route_name = "entity.$entity_type_id.recurly_signup";
      $authenticated_route = \Drupal::service('router.route_provider')->getRouteByName($authenticated_route_name);
      return $this->redirect($authenticated_route_name, [
        'user' => \Drupal::currentUser()->id(),
      ], $authenticated_route->getOptions());
    }

    $entity = $route_match->getParameter($entity_type_id);
    $entity_type = \Drupal::entityTypeManager()->getDefinition($entity_type_id)->getLowercaseLabel();

    // Initialize the Recurly client with the site-wide settings.
    if (!recurly_client_initialize()) {
      return ['#markup' => $this->t('Could not initialize the Recurly client.')];
    }

    $mode = $subscription_id ? self::SELECT_PLAN_MODE_CHANGE : self::SELECT_PLAN_MODE_SIGNUP;
    $subscriptions = [];

    // If loading an existing subscription.
    if ($subscription_id) {
      if ($subscription_id === 'latest') {
        $local_account = recurly_account_load(['entity_type' => $entity_type, 'entity_id' => $entity->id()], TRUE);
        $subscriptions = recurly_account_get_subscriptions($local_account->account_code, 'active');
        $subscription = reset($subscriptions);
        $subscription_id = $subscription->uuid;
      }
      else {
        try {
          $subscription = \Recurly_Subscription::get($subscription_id);
          $subscriptions[$subscription->uuid] = $subscription;
        }
        catch (\Recurly_NotFoundError $e) {
          throw new NotFoundHttpException($this->t('Subscription not found'));
        }
      }
      $currency = $subscription->plan->currency;
    }
    // If signing up to a new subscription, ensure the user doesn't have a plan.
    elseif (\Drupal::currentUser()->isAuthenticated()) {
      $currency = isset($currency) ? $currency : $this->config('recurly.settings')->get('recurly_default_currency');
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
    $enabled_plan_keys = $this->config('recurly.settings')->get('recurly_subscription_plans') ?: [];
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

  /**
   * Redirect anonymous users to registration when attempting to select plans.
   */
  public function redirectToRegistration() {
    drupal_set_message(t('Create an account, or log in with an existing account, before selecting a plan.'), 'warning');
    return $this->redirect('user.register');
  }

}
