<?php
/**
 * @file
 * Contains \Drupal\recurly\Access\RecurlyAccessReactivateLatest.
 */

namespace Drupal\recurly\Access;

use Drupal\Core\Access\AccessResult;

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
