<?php
/**
 * @file
 * Contains \Drupal\recurly\Access\RecurlyAccessList.
 */

namespace Drupal\recurly\Access;

use Drupal\Core\Access\AccessResult;

/**
 * Checks if the list operation should be accessible.
 */
class RecurlyAccessList extends RecurlyAccess {

  /**
   * {@inheritdoc}
   */
  public function access() {
    $this->setLocalAccount();
    if ($this->localAccount || $this->subscriptionPlans) {
      return AccessResult::allowed();
    }
    return AccessResult::forbidden();
  }

}
