<?php

/**
 * @file
 * Contains \Drupal\recurly\RecurlyUrlManager.
 */

namespace Drupal\recurly;

class RecurlyUrlManager {

  /**
   * Generate the subdomain to use for the current account.
   *
   * @param $path string
   *   A path string.
   *
   * @return string
   *   The Recurly URL for the current account w/optional path appended.
   */
  public function hostedUrl($path = '', $subdomain = NULL) {
    if (!$subdomain) {
      $subdomain = \Drupal::config('recurly.settings')->get('recurly_subdomain');
    }

    return \Drupal\Core\Url::fromUri('https://' . $subdomain . '.recurly.com/' . $path)->getUri();
  }
}
