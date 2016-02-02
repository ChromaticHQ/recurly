<?php
/**
 * @file
 * Contains \Drupal\recurly\Access\RecurlyAccessChangePlanLatest.
 */

namespace Drupal\recurly\Access;

use Drupal\Core\Access\AccessResult;

/**
 * Checks if the change latest operation should be accessible.
 */
class RecurlyAccessChangePlanLatest extends RecurlyAccess {

  /**
   * {@inheritdoc}
   */
  public function access() {
    if ($this->recurlySubscriptionMax == 1) {
      $this->setLocalAccount();
      $active_subscriptions = $this->localAccount ? recurly_account_get_subscriptions($this->localAccount->account_code, 'active') : [];
      $active_subscription = reset($active_subscriptions);
      if (count($this->subscriptionPlans) && !empty($active_subscription)) {
        return AccessResult::allowed();
      }
    }

    return AccessResult::forbidden();
  }

}
