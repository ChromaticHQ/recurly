<?php /**
 * @file
 * Contains \Drupal\recurly\Controller\DefaultController.
 */

namespace Drupal\recurly\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Default controller for the recurly module.
 */
class DefaultController extends ControllerBase {

  public function recurly_subscription_plans_overview() {
    // Initialize the Recurly client with the site-wide settings.
    if (!recurly_client_initialize()) {
      return t('Could not initialize the Recurly client.');
    }

    try {
      $plans = recurly_subscription_plans();
    }
    catch (Recurly_Error $e) {
      return t('No plans could be retrieved from Recurly. Recurly reported the following error: "@error"', [
        '@error' => $e->getMessage()
        ]);
    }
    return \Drupal::formBuilder()->getForm('recurly_subscription_plans_form', $plans);
  }

  public function recurly_subscription_redirect($account_code) {
    $account = recurly_account_load(['account_code' => $account_code], TRUE);
    if ($account) {
      drupal_goto($account->entity_type . '/' . $account->entity_id . '/subscription');
    }
    else {
      return MENU_NOT_FOUND;
    }
  }
}
