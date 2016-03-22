<?php
/**
 * @file
 * Contains \Drupal\recurly\Access\RecurlyAccessLocalAccount.
 */

namespace Drupal\recurly\Access;

use Drupal\Core\Access\AccessResult;

/**
 * Checks access for displaying a given operation.
 *
 * Eventually each operation in this class will be put into its own class and
 * the routes will be updated to check services that interface with each of
 * these classes.
 */
class RecurlyAccessLocalAccount extends RecurlyAccess {

  /**
   * {@inheritdoc}
   */
  public function access() {
    $this->setLocalAccount();
    if (!empty($this->localAccount)) {
      return AccessResult::allowed();
    }

    return AccessResult::forbidden();
  }

}
