<?php
/**
 * @file
 * Contains \Drupal\recurly\Access\RecurlyAccessCancelLatest.
 */

namespace Drupal\recurly\Access;


/**
 * Checks if the cancel operation should be accessible.
 */
class RecurlyAccessCancelLatest extends RecurlyAccess {

  /**
   * {@inheritdoc}
   */
  public function access() {
    $this->setLocalAccount();
    return $this->switchSubscriptionState('active');
  }

}
