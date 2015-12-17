<?php

/**
 * @file
 * Contains \Drupal\recurly\Controller\RecurlySubscriptionListController.
 */

namespace Drupal\recurly\Controller;

use Drupal\Component\Utility\SafeMarkup;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityInterface;
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
      if ($url = recurly_url('select_plan', ['entity_type' => $entity_type, 'entity' => $entity])) {
        return $this->redirect($url->getRouteName(), $url->getRouteParameters());
      }
      else {
        throw new NotFoundHttpException();
      }
    }

    // Unlikely that we'd have more than 50 subscriptions, but you never know.
    $per_page = 50;
    $subscription_type = \Drupal::config('recurly.settings')->get('recurly_subscription_display');
    $subscription_list = \Recurly_SubscriptionList::getForAccount($account->account_code, ['per_page' => $per_page]);
    $page_subscriptions = $this->pagerResults($subscription_list, $per_page);

    $subscriptions['subscriptions']['#attached']['library'][] = 'recurly/recurly.default';

    $total_displayed = 0;
    foreach ($page_subscriptions as $subscription) {
      // Do not show subscriptions that are not active if only showing active.
      // TODO: Figure out if Recurly_SubscriptionList can only return active
      // subscriptions to begin with, rather than hiding them like this.
      if ($subscription->state === 'expired' && $subscription_type === 'live') {
        continue;
      }
      $total_displayed++;

      // Determine the state of this subscription.
      $states = $this->subscriptionGetStates($subscription, $account);

      // Ensure that 'canceled' is listed before 'in_trial' in the list of
      // possible states, as this can influence what the summary looks like and
      // displaying the summary for a canceled account even when in trial is
      // less confusing for users who have cancelled their in trial account.
      if (in_array('canceled', $states)) {
        sort($states);
      }

      if (\Drupal::config('recurly.settings')->get('recurly_subscription_max') === '1') {
        $links = FALSE;
      }
      else {
        $links = $this->subscriptionLinks($entity_type, $entity, $subscription, $account, $states);
      }

      $plan = $subscription->plan;
      $add_ons = [];
      $total = 0;
      foreach ($subscription->subscription_add_ons as $add_on) {
        // Fully load the add on to get the name attribute.
        $full_add_on = \Recurly_Addon::get($plan->plan_code, $add_on->add_on_code);
        $add_ons[$add_on->add_on_code] = [
          'add_on_code' => $add_on->add_on_code,
          'name' => SafeMarkup::checkPlain($full_add_on->name),
          'quantity' => SafeMarkup::checkPlain($add_on->quantity),
          'cost' => recurly_format_currency($add_on->unit_amount_in_cents, $subscription->currency),
        ];
        $total += $add_on->unit_amount_in_cents * $add_on->quantity;
      }
      $total += $subscription->unit_amount_in_cents * $subscription->quantity;

      $message = '';
      foreach ($states as $state) {
        $message = $this->subscriptionStateMessage($state, [
          'account' => $account,
          'subscription' => $subscription,
        ]);
        break;
      }

      $subscriptions['subscriptions'][$subscription->uuid] = [
        '#theme' => ['recurly_subscription_summary'],
        '#plan_code' => $plan->plan_code,
        '#plan_name' => SafeMarkup::checkPlain($plan->name),
        '#state_array' => $states,
        '#state_status' => recurly_format_state(reset($states)),
        '#period_end_header' => $this->periodEndHeaderString($states),
        '#cost' => recurly_format_currency($subscription->unit_amount_in_cents, $subscription->currency),
        '#quantity' => $subscription->quantity,
        '#add_ons' => $add_ons,
        '#start_date' => recurly_format_date($subscription->activated_at),
        '#end_date' => isset($subscription->expires_at) ? recurly_format_date($subscription->expires_at) : NULL,
        '#current_period_start' => recurly_format_date($subscription->current_period_started_at),
        '#current_period_ends_at' => recurly_format_date($subscription->current_period_ends_at),
        '#total' => recurly_format_currency($total, $subscription->currency),
        '#subscription_links' => [
          '#theme' => 'links',
          '#links' => $links,
          '#attributes' => ['class' => ['inline', 'links']],
        ],
        '#message' => $message,
        '#subscription' => $subscription,
        '#account' => $account,
      ];
    }

    $subscriptions['pager'] = [
      '#theme' => 'pager',
      '#access' => $subscription_list->count() > $per_page,
    ];

    \Drupal::moduleHandler()->alter('recurly_subscription_list_page', $subscriptions);

    // If the user doesn't have any active subscriptions, redirect to signup.
    if (count(\Drupal\Core\Render\Element::children($subscriptions['subscriptions'])) === 0) {
      return $this->redirect('recurly.subscription_signup', ['entity' => $entity->id()]);
    }

    return $subscriptions;
  }

  /**
   * Build a list of links to manage a subscription.
   */
  protected function subscriptionLinks($entity_type, $entity, $subscription, $account, $states) {
    // Generate the list of links for this subscription.
    $url_context = [
      'entity_type' => $entity_type,
      'entity' => $entity,
      'subscription' => $subscription,
      'account' => $account,
    ];

    $links = [];
    if ($subscription->state === 'active') {
      $links['change'] = [
        'href' => recurly_url('change_plan', $url_context),
        'external' => TRUE,
        'title' => t('Change plan'),
      ];
      $links['cancel'] = [
        'href' => recurly_url('cancel', $url_context),
        'external' => TRUE,
        'title' => t('Cancel'),
        // Pass in the past_due flag to accurately calculate refunds.
        'query' => in_array('past_due', $states) ? ['past_due' => '1'] : NULL,
      ];
    }
    elseif ($subscription->state === 'canceled') {
      $links['reactivate'] = [
        'href' => recurly_url('reactivate', $url_context),
        'external' => TRUE,
        'title' => t('Reactivate'),
      ];
    }
    // Allow other modules to provide links, perhaps "suspend" for example.
    \Drupal::moduleHandler()->alter('recurly_subscription_links', $links);

    return $links;
  }

  /**
   * Returns a message for subscription if the subscription state is not active.
   */
  protected function subscriptionStateMessage($state, $context) {
    switch ($state) {
      case 'active':
        return '';

      case 'closed':
        return t('This account is closed.');

      case 'in_trial':
        return t('Currently in trial period.');

      case 'past_due':
        $url = recurly_url('update_billing', $context);
        if ($url) {
          return t('This account is past due. Please <a href="!url">update your billing information</a>.', ['!url' => $url]);
        }
        else {
          return t('This account is past due. Please contact an administrator to update your billing information.');
        }
      case 'canceled':
        $url = recurly_url('reactivate', $context);
        if ($url) {
          return t('This plan is canceled and will not renew. You may <a href="!url">reactivate the plan</a> to resume billing.', ['!url' => $url]);
        }
        else {
          return t('This plan is canceled and will not renew.');
        }
      case 'expired':
        $url = recurly_url('select_plan', $context);
        if ($url) {
          return t('This plan has expired. Please <a href="!url">purchase a new subscription</a>.', ['!url' => $url]);
        }
        else {
          return t('This plan has expired.');
        }
      case 'pending_subscription':
        return t('This plan will be changed to @plan on @date.', ['@plan' => $context['subscription']->pending_subscription->plan->name, '@date' => recurly_format_date($context['subscription']->current_period_ends_at)]);

      case 'future':
        return t('This plan has not started yet. Please contact support if you have any questions.');

      default:
        return '';
    }
  }

  /**
   * Get a list of all states in which a subscription exists currently.
   *
   * @param object $subscription
   *   A Recurly subscription object.
   * @param object $account
   *   A Recurly account object.
   */
  protected function subscriptionGetStates($subscription, $account) {
    static $past_due = [];
    $states = [];

    // Determine if in a trial.
    if ($subscription->trial_started_at && $subscription->trial_ends_at) {
      $subscription->trial_started_at->setTimezone(new DateTimeZone('UTC'));
      $subscription->trial_ends_at->setTimezone(new DateTimeZone('UTC'));
      $start = $subscription->trial_started_at->format('U');
      $end = $subscription->trial_ends_at->format('U');
      if (REQUEST_TIME > $start && REQUEST_TIME < $end) {
        $states[] = 'in_trial';
      }
    }

    // Determine if non-renewing.
    if (!empty($subscription->total_billing_cycles)) {
      $states[] = 'non_renewing';
    }

    // Retrieve past due subscriptions.
    if (!isset($past_due[$account->account_code])) {
      $subscriptions = \Recurly_SubscriptionList::getForAccount($account->account_code, ['state' => 'past_due']);
      $past_due[$account->account_code] = [];
      foreach ($subscriptions as $past_due_subscription) {
        $past_due[$account->account_code][] = $past_due_subscription->uuid;
      }
    }
    if (in_array($subscription->uuid, $past_due[$account->account_code])) {
      $states[] = 'past_due';
    }

    // Subscriptions that have pending changes.
    if (!empty($subscription->pending_subscription)) {
      $states[] = 'pending_subscription';
    }

    $states[] = $subscription->state;
    return $states;
  }

  /**
   * Utility function to retrieve a specific page of results from Recurly_Pager.
   *
   * @param object $pager_object
   *   Any object that extends a Recurly_Pager object, such as a
   *   Recurly_InvoiceList, Recurly_SubscriptionList, or
   *   Recurly_TransactionList.
   * @param int $per_page
   *   The number of items to display per page.
   * @param int $page_num
   *   The desired page number to display. Usually automatically determined from
   *   the URL.
   */
  protected function pagerResults($pager_object, $per_page, $page_num = NULL) {
    if (!isset($page_num)) {
      $page_num = isset($_GET['page']) ? (int) $_GET['page'] : 0;
    }

    // Fast forward the list to the current page.
    $start = $page_num * $per_page;
    for ($n = 0; $n < $start; $n++) {
      $pager_object->next();
    }

    // Populate $page_results with the current page.
    $total = $pager_object->count();
    $page_end = min($start + $per_page, $total);
    $page_results = array();
    for ($n = $start; $n < $page_end; $n++) {
      $item = $pager_object->current();
      $page_results[$item->uuid] = $item;
      $pager_object->next();
    }

    pager_default_initialize($total, $per_page);

    return $page_results;
  }

  /**
   * Generates table header string for subscription period end.
   *
   * @param array $states
   *   An array of subscription states.
   *
   * @return string
   *   Text to be used as the table header when the subscription period ends.
   */
  protected function periodEndHeaderString($states) {
    if (count(array_intersect(['canceled', 'non_renewing', 'expired'], $states))
      && !in_array('in_trial', $states)) {
      return t('Expiration Date');
    }
    return t('Next Invoice');
  }

}
