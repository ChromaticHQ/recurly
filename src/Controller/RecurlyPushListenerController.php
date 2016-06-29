<?php

namespace Drupal\recurly\Controller;

use Drupal\Component\Utility\SafeMarkup;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Render\HtmlResponse;

/**
 * Default controller for the recurly module.
 */
class RecurlyPushListenerController extends ControllerBase {

  /**
   * Process push notification.
   *
   * @param string $key
   *   Recurly listener key.
   * @param string $subdomain
   *   Recurly account subdomain.
   */
  public function processPushNotification($key, $subdomain = NULL) {
    $notification = NULL;

    // If no subdomain was derived from the URL or the default account subdomain
    // was specified...
    if (empty($subdomain) || $subdomain == \Drupal::config('recurly.settings')->get('recurly_subdomain')) {
      // Ensure the push notification was sent to the proper URL.
      if ($key != \Drupal::config('recurly.settings')->get('recurly_listener_key')) {
        // Log the failed attempt and bail.
        $url_key_error_text = 'Incoming push notification did not contain the proper URL key.';
        \Drupal::logger('recurly')->warning($url_key_error_text, []);
        return new HtmlResponse($url_key_error_text, HtmlResponse::HTTP_FORBIDDEN);
      }

      // Initialize the Recurly client with the default account settings.
      recurly_client_initialize();

      // Retrieve the POST XML and create a notification object from it.
      $post_xml = file_get_contents('php://input');
      $notification = new \Recurly_PushNotification($post_xml);

      // Bail if this is an empty or invalid notification.
      if (empty($notification) || empty($notification->type)) {
        return new HtmlResponse('Empty or invalid notification.', HtmlResponse::HTTP_BAD_REQUEST);
      }

      // Log the incoming push notification if enabled.
      if (\Drupal::config('recurly.settings')->get('recurly_push_logging')) {
        \Drupal::logger('recurly')->notice('Incoming %type: <pre>@notification</pre>', [
          '%type' => $notification->type,
          '@notification' => print_r($notification, TRUE),
        ]);
      }

      // If this is a new, canceled, or updated account set the database record.
      if (in_array($notification->type, [
        'new_account_notification',
        'new_subscription_notification',
        'canceled_account_notification',
        'reactivated_account_notification',
        'billing_info_updated_notification',
      ])) {
        // Retrieve the full account record from Recurly.
        try {
          $recurly_account = \Recurly_Account::get($notification->account->account_code);
        }
        catch (Recurly_NotFoundError $e) {
          drupal_set_message($this->t('Account not found'));
          watchdog_exception('recurly', $e);
        }

        // If we couldn't get anything, just attempt to use the submitted data.
        if (empty($recurly_account)) {
          $recurly_account = $notification->account;
        }

        // Look for a pre-existing local record.
        $local_account = recurly_account_load([
          'account_code' => $recurly_account->account_code,
        ], TRUE);

        // If no local record exists and we've specified to create it...
        if (empty($local_account)) {
          // First try to match based on the account code.
          // i.e. "user-1" would match the user with UID 1.
          $parts = explode('-', $recurly_account->account_code);
          $entity_type = \Drupal::config('recurly.settings')->get('recurly_entity_type');
          if ($parts[0] === $entity_type) {
            if (isset($parts[1]) && is_numeric($parts[1]) && ($entity = \Drupal::entityManager()->getStorage($parts[0], [
              $parts[1],
            ]))) {
              recurly_account_save($recurly_account, $entity_type, $parts[1]);
            }
          }
          // Attempt to find a matching user account by e-mail address if the
          // enabled entity type is user.
          elseif ($entity_type === 'user' && ($user = user_load_by_mail($recurly_account->email))) {
            recurly_account_save($recurly_account, 'user', $user->uid);
          }
        }
        elseif (!empty($local_account)) {
          // Otherwise if a local record was found and we want to keep it
          // synchronized, save it again and let any modules respond.
          recurly_account_save($recurly_account, $local_account->entity_type, $local_account->entity_id);
        }
      }

      // Allow other modules to respond to incoming notifications.
      \Drupal::moduleHandler()->invokeAll('recurly_process_push_notification', [
        $subdomain,
        $notification,
      ]);
    }
    $subdomain_error_text = 'Incoming push notification did not contain the proper subdomain key.';
    \Drupal::logger('recurly')->warning($subdomain_error_text, []);
    return new HtmlResponse($subdomain_error_text, HtmlResponse::HTTP_FORBIDDEN);
  }

}
