<?php

namespace Drupal\recurly\Access;

use Drupal\Core\Access\AccessResult;

/**
 * Checks if the signup operation should be accessible.
 */
class RecurlyAccessSignUp extends RecurlyAccess {

  /**
   * {@inheritdoc}
   */
  public function access() {
    if ($this->recurlySubscriptionMax == 1) {
      $this->setLocalAccount();
      $active_subscriptions = $this->localAccount ? recurly_account_get_subscriptions($this->localAccount->account_code, 'active') : [];
      if (isset($this->localAccount) || isset($active_subscriptions)) {
        return AccessResult::allowed();
      }
    }
    elseif ($this->recurlySubscriptionMax != 1) {
      return AccessResult::allowed();
    }

    return AccessResult::forbidden();
  }

}
