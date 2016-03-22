<?php

/**
 * @file
 * Contains \Drupal\recurly\RecurlyUrlManager.
 */

namespace Drupal\recurly;

/**
 * RecurlyUrlManager.
 */
class RecurlyUrlManager {

  /**
   * Generate the subdomain to use for the current account.
   *
   * @param string $path
   *   A path string.
   * @param string $subdomain
   *   A subdomain string.
   *
   * @return \Drupal\Core\Url
   *   Returns a \Drupal\Core\Url object.
   */
  public function hostedUrl($path = '', $subdomain = NULL) {
    if (!$subdomain) {
      $subdomain = \Drupal::config('recurly.settings')->get('recurly_subdomain');
    }

    return \Drupal\Core\Url::fromUri('https://' . $subdomain . '.recurly.com/' . $path);
  }

  /**
   * Returns an edit URL for a subscription plan.
   *
   * @param object $plan
   *   The subscription plan object returned by the Recurly client.
   *
   * @return \Drupal\Core\Url
   *   Returns a \Drupal\Core\Url object.
   */
  public function planEditUrl($plan) {
    return $this->hostedUrl('company/plans/' . $plan->plan_code);
  }

}
