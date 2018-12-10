<?php

namespace Drupal\recurly;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Messenger\MessengerTrait;

/**
 * Interacts with Recurly's client library.
 */
class RecurlyClient {
  use MessengerTrait;

  const LIBRARY_PHP_FILE = '/lib/recurly.php';
  const COMPOSER_LIBRARY_PATH = DRUPAL_ROOT . '/../vendor/recurly/recurly-client';

  const ERROR_MESSAGE_MISSING_API_KEY = 'The Recurly private API key is not configured.';
  const ERROR_MESSAGE_MISSING_SUBDOMAIN = 'The Recurly subdomain is not configured.';
  const ERROR_MESSAGE_MISSING_LIBRARY = 'Could not find the Recurly PHP client library.';

  /**
   * This module's settings.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $moduleSettings;

  /**
   * The logging service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $logger;

  /**
   * Class Constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_service
   *   The Recurly configuration.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_service
   *   The logger.
   */
  public function __construct(ConfigFactoryInterface $config_service, LoggerChannelFactoryInterface $logger_service) {
    $this->moduleSettings = $config_service->get('recurly.settings');
    $this->logger = $logger_service->get('recurly');
    $this->initialize();
  }

  /**
   * Initializes the Recurly API client with a given set of account settings.
   *
   * @param array $account_settings
   *   An array of Recurly account settings including the following keys or NULL
   *   to use the site-wide account settings.
   *   - username: the API username to use
   *   - password: the API password for the given username
   *   - subdomain: the subdomain configured for your Recurly account.
   *   - environment: the current environment of the given account, either
   *     'sandbox' or 'production'.
   * @param bool $reset
   *   TRUE if the initialization should be reset; FALSE otherwise.
   *
   * @return bool
   *   TRUE or FALSE indicating whether or not the client was initialized with
   *   the specified account settings.
   */
  public function initialize(array $account_settings = NULL, $reset = FALSE) {
    static $initialized = FALSE;

    // Skip the process if we're not setting up a new connection and we're
    // already set up with a configuration.
    if ($initialized && !$reset) {
      return TRUE;
    }

    // If no settings array was given, use the default account settings.
    if (empty($account_settings)) {
      $account_settings = $this->getDefaultAccountSettings();
    }

    // Ensure that the mandatory settings have been entered.
    if (empty($account_settings['api_key'])) {
      $message = self::ERROR_MESSAGE_MISSING_API_KEY;
      $this->messenger()->addError($message);
      $this->logger->error($message);
      return FALSE;
    }
    if (empty($account_settings['subdomain'])) {
      $message = self::ERROR_MESSAGE_MISSING_SUBDOMAIN;
      $this->messenger()->addError($message);
      $this->logger->error($message);
      return FALSE;
    }
    if (!$this->loadLibrary($account_settings)) {
      return FALSE;
    }

    // Required for the API.
    \Recurly_Client::$apiKey = $account_settings['api_key'];

    $initialized = TRUE;
    return TRUE;
  }

  /**
   * Fetches the default account settings.
   *
   * @return array
   *   The settings.
   */
  protected function getDefaultAccountSettings() {
    return [
      'api_key' => $this->moduleSettings->get('recurly_private_api_key'),
      'subdomain' => $this->moduleSettings->get('recurly_subdomain'),
      'public_key' => $this->moduleSettings->get('recurly_public_key'),
    ];
  }

  /**
   * Ensures that Recurly's PHP client library exists and is loaded.
   *
   * @return bool
   *   TRUE if successful; FALSE otherwise.
   */
  public static function loadLibrary() {
    $libraries_path = static::getLibrariesPath();

    switch (TRUE) {
      case file_exists(static::COMPOSER_LIBRARY_PATH . static::LIBRARY_PHP_FILE):
        // Autoloaded; nothing to do here.
        break;

      case $libraries_path && file_exists($libraries_path):
        require_once $libraries_path;
        break;

      default:
        $message = self::ERROR_MESSAGE_MISSING_LIBRARY;
        \Drupal::messenger()->addError($message);
        \Drupal::logger('recurly')->error($message, []);
        return FALSE;
    }

    return TRUE;
  }

  /**
   * Fetches the patch to the library managed by the Libraries API module.
   *
   * @return string
   *   The path if there is one. If not, the empty string will be returned.
   */
  protected static function getLibrariesPath() {
    return function_exists('libraries_get_path') ? libraries_get_path('recurly') . static::LIBRARY_PHP_FILE : '';
  }

}
