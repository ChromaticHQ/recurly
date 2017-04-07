<?php

namespace Drupal\recurly;

use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * RecurlyTokenManager.
 */
class RecurlyTokenManager {

  /**
   * The Recurly settings.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $recurlySettings;

  /**
   * Constructs the Recurly token manager.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config service.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    $this->recurlySettings = $config_factory->get('recurly.settings');
  }

  /**
   * Get the token mapping for Recurly variables with defaults populated.
   */
  public function tokenMapping() {
    $mapping = $this->recurlySettings->get('recurly_token_mapping') ?: [];
    $mapping += [
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
    ];
    return $mapping;
  }

}
