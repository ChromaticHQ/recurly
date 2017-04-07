<?php

namespace Drupal\recurly;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Url;

/**
 * RecurlyUrlManager.
 */
class RecurlyUrlManager {

  /**
   * The Recurly settings.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $recurlySettings;

  /**
   * Constructs the Recurly URL manager.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config service.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    $this->recurlySettings = $config_factory->get('recurly.settings');
  }

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
      $subdomain = $this->recurlySettings->get('recurly_subdomain');
    }

    return Url::fromUri('https://' . $subdomain . '.recurly.com/' . $path);
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
