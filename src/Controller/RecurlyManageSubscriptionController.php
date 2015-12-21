<?php
/**
 * @file
 * Contains \Drupal\recurly\Controller\RecurlyManageSubscriptionController.
 */

namespace Drupal\recurly\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Render\HtmlResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Default controller for the recurly module.
 */
class RecurlyManageSubscriptionController extends ControllerBase {

  /**
   * Redirects a Recurly account code subscription management page.
   */
  function subscriptionRedirect($account_code) {
    $account = recurly_account_load(['account_code' => $account_code], TRUE);
    if ($account) {
      return $this->redirect('recurly.subscription_list', ['entity' => $account->entity_id]);
    }
    else {
      throw new NotFoundHttpException();
    }
  }

}
