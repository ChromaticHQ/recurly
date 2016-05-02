<?php

namespace Drupal\recurly\Access;


/**
 * Checks if the reactivate latest operation should be accessible.
 */
class RecurlyAccessReactivateLatest extends RecurlyAccess {

  /**
   * {@inheritdoc}
   */
  public function access() {
    $this->setLocalAccount();
    return $this->switchSubscriptionState('canceled');
  }

}
