<?php
/**
 * @file
 * Contains \Drupal\recurly\Access\RecurlyAccessDefault.
 */

/**
 * Eventually each operation in this class will be put into its own class and
 * the routes will be updated to check services that interface with each of
 * these classes.
 */

namespace Drupal\recurly\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\Routing\Route;

/**
 * Checks access for displaying a given operation.
 */
class RecurlyAccessDefault extends RecurlyAccess {

  /**
   * {@inheritdoc}
   */
  public function access(Route $route, RouteMatchInterface $route_match, $operation = NULL) {
    $entity_type_id = \Drupal::config('recurly.settings')->get('recurly_entity_type') ?: 'user';
    $entity = $route_match->getParameter($entity_type_id);
    $subscription_plans = \Drupal::config('recurly.settings')->get('recurly_subscription_plans') ?: [];
    $recurly_subscription_max = \Drupal::config('recurly.settings')->get('recurly_subscription_max');
    $local_account = recurly_account_load(['entity_type' => $entity->getEntityType()->getLowercaseLabel(), 'entity_id' => $entity->id()], TRUE);

    if ($operation == 'select_plan') {
      // This tab is only visible when visited directly or if multiple plans are
      // allowed.
      if (!empty($subscription_plans) && $this->pathIsSignup($route) || $recurly_subscription_max != 1) {
        return AccessResult::allowed();
      }
    }
    // These pages are only accessible if using the single-page mode. This
    // requires loading the latest active account for an entity.
    elseif ($recurly_subscription_max == 1) {
      $active_subscriptions = $local_account ? recurly_account_get_subscriptions($local_account->account_code, 'active') : [];
      $active_subscription = reset($active_subscriptions);
      if ($operation === 'change_plan_latest') {
        if (!empty($local_account) && count($subscription_plans) && !empty($active_subscription)) {
          return AccessResult::allowed();
        }
        return AccessResult::forbidden();
      }
      elseif ($operation == 'cancel_latest') {
        if (!empty($local_account) && !empty($active_subscription) && $active_subscription->state == 'active') {
          return AccessResult::allowed();
        }
        return AccessResult::forbidden();
      }
      elseif ($operation == 'reactivate_latest') {
        if (!empty($local_account) && !empty($active_subscription) && $active_subscription->state == 'canceled') {
          return AccessResult::allowed();
        }
        return AccessResult::forbidden();
      }
      // @FIXME: $_POST has been removed.
      // @see https://www.drupal.org/node/2150267
      elseif ($operation === 'signup') {
        // POST is included here to allow the signup form to finish processing,
        // in the event that the push notification comes so fast it finishes
        // before Drupal processes the form that contained a Recurly.js element.
        // return empty($local_account) || empty($active_subscriptions) || !empty($_POST);
        if (isset($local_account) || isset($active_subscriptions)) {
          return AccessResult::allowed();
        }
        return AccessResult::forbidden();
      }
    }
    elseif (in_array($operation, [
      'change_plan_latest',
      'cancel_latest',
      'reactivate_latest',
    ])) {
      return AccessResult::forbidden();
    }
    elseif ($operation == 'signup' && $recurly_subscription_max != 1) {
      return AccessResult::allowed();
    }

    if (!empty($local_account)) {
      return AccessResult::allowed();
    }
    return AccessResult::forbidden();
  }

}
