<?php

/**
 * @file
 * Contains \Drupal\recurly\RecurlyTokenManager.
 */

namespace Drupal\recurly;

class RecurlyTokenManager {

  /**
   * Get the token mapping for Recurly variables with defaults populated.
   */
  function tokenMapping() {
    $mapping = \Drupal::config('recurly.settings')->get('recurly_token_mapping') ?: array();
    $mapping += array(
      'email' => '[user:mail]',
      'username' => '[user:name]',
      'first_name' => '',
      'last_name' => '',
      'company_name' => '',
      'address1' => '',
      'address2' => '',
      'city' => '',
      'state' => '',
      'zip' => '',
      'country' => '',
      'phone' => '',
    );
    return $mapping;
  }
}
