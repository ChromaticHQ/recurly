<?php

namespace Drupal\recurly;

use Drupal\Component\Utility\Html;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\StringTranslation\TranslationInterface;

/**
 * Constants for "state" strings.
 */
const RECURLY_STATE_ACTIVE = 'active';
const RECURLY_STATE_CANCELED = 'canceled';
const RECURLY_STATE_EXPIRED = 'expired';
const RECURLY_STATE_FUTURE = 'future';
const RECURLY_STATE_PENDING_SUBSCRIPTION = 'pending_subscription';
const RECURLY_STATE_IN_TRIAL = 'in_trial';
const RECURLY_STATE_LIVE = 'live';
const RECURLY_STATE_PAST_DUE = 'past_due';

/**
 * RecurlyFormatManager.
 */
class RecurlyFormatManager {

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The Recurly settings.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $recurlySettings;

  /**
   * The translation manager service.
   *
   * @var \Drupal\Core\StringTranslation\TranslationInterface
   */
  protected $stringTranslation;

  /**
   * Constructs the Recurly format manager.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config service.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $translation_manager
   *   The translation manager service.
   */
  public function __construct(
    ModuleHandlerInterface $module_handler,
    ConfigFactoryInterface $config_factory,
    TranslationInterface $translation_manager
    ) {
    $this->moduleHandler = $module_handler;
    $this->recurlySettings = $config_factory->get('recurly.settings');
    $this->stringTranslation = $translation_manager;
  }

  /**
   * Format a Recurly subscription state.
   */
  public function formatState($state) {
    switch ($state) {
      case RECURLY_STATE_ACTIVE:
        return $this->stringTranslation->translate('Active');

      case RECURLY_STATE_CANCELED:
        return $this->stringTranslation->translate('Canceled (will not renew)');

      case RECURLY_STATE_EXPIRED:
        return $this->stringTranslation->translate('Expired');

      case RECURLY_STATE_FUTURE:
        return $this->stringTranslation->translate('Future Activation');

      case RECURLY_STATE_PENDING_SUBSCRIPTION:
        return $this->stringTranslation->translate('Switching to new plan');

      case RECURLY_STATE_IN_TRIAL:
        return $this->stringTranslation->translate('Trial');

      case RECURLY_STATE_LIVE:
        return $this->stringTranslation->translate('Live');

      case RECURLY_STATE_PAST_DUE:
        return $this->stringTranslation->translate('Past Due');
    }
  }

  /**
   * Format a date for use in invoices.
   */
  public function formatDate($date) {
    $format = $this->recurlySettings->get('recurly_date_format');
    if (is_object($date)) {
      $date->setTimezone(new \DateTimeZone('UTC'));
      $timestamp = $date->format('U');
    }
    else {
      $timestamp = strtotime($date);
    }

    return is_numeric($timestamp) ? format_date($timestamp, $format) : NULL;
  }

  /**
   * Format a price for display.
   */
  public function formatCurrency($price_in_cents, $currency, $html = FALSE) {
    $currencies = recurly_currency_list();
    $currency_info = isset($currencies[$currency]) ? $currencies[$currency] : ['', ' ' . $currency];
    $prefix = $currency_info[0] ? $currency_info[0] : '';
    $suffix = $currency_info[1] ? $currency_info[1] : '';
    $thousands_separator = isset($currency_info[2]) ? $currency_info[2] : ',';
    $decimal_separator = isset($currency_info[3]) ? $currency_info[3] : '.';
    $decimals = isset($currency_info[4]) ? $currency_info[4] : 2;
    $rounding_step = isset($currency_info[5]) ? $currency_info[5] : NULL;

    // Commerce module provides a more flexible and complete currency formatter.
    if ($this->moduleHandler->moduleExists('commerce')) {
      $formatted = commerce_currency_format($price_in_cents, $currency, NULL, TRUE);
    }
    else {
      // Convert to a decimal amount.
      $float = $price_in_cents / 100;

      // Round the amount if necessary i.e. Francs round up to the nearest 0.05.
      if ($rounding_step) {
        $modifier = 1 / $rounding_step;
        $float = round($float * $modifier) / $modifier;
      }

      // Format the number.
      $formatted = $prefix . number_format($float, $decimals, $decimal_separator, $thousands_separator) . $suffix;
    }

    // Wrap each part in HTML if requested.
    if ($html) {
      $amount_string = '';
      $amount_array = [];
      preg_match('/^(' . preg_quote($prefix) . ')([0-9' . preg_quote($thousands_separator) . ']+)' . preg_quote($decimal_separator) . '([0-9]+)(' . preg_quote($suffix) . ')?$/', $formatted, $amount_array);
      if ($amount_array[1]) {
        $amount_string .= '<span class="currency-prefix">' . $amount_array[1] . '</span>';
      }
      if ($amount_array[2] !== '') {
        $amount_string .= '<span class="currency-dollars">' . $amount_array[2] . '</span>';
      }
      $amount_string .= $decimal_separator;
      if ($amount_array[3] !== '') {
        $amount_string .= '<span class="currency-cents">' . $amount_array[3] . '</span>';
      }
      if ($amount_array[4]) {
        $amount_string .= '<span class="currency-suffix">' . $amount_array[4] . '</span>';
      }
      $formatted = $amount_string;
    }

    return $formatted;
  }

  /**
   * Format an interval of time in a human-readable way.
   */
  public function formatPriceInterval($amount, $count, $unit, $html = FALSE) {
    // Trial only pricing.
    if ($amount === NULL) {
      if ($unit == 'days') {
        return $this->stringTranslation->formatPlural($count, '1 day trial', '@count day trial');
      }
      else {
        return $this->stringTranslation->formatPlural($count, '1 month trial', '@count month trial');
      }
    }
    // Set default values.
    $time_indicator = 'per';
    $time_unit = NULL;
    $time_length = NULL;
    // Exactly 1 day or 1 month.
    if ($count == 1) {
      switch ($unit) {
        case 'days':
          $time_unit = 'day';
          break;

        case 'months':
          $time_unit = 'month';
          break;
      }
    }
    // Exactly 1 week.
    elseif ($count == 7 && $unit == 'days') {
      $time_unit = 'week';
    }
    // Exactly 1 year.
    elseif ($count == 12 && $unit == 'months') {
      $time_unit = 'year';
    }
    else {
      switch ($unit) {
        case 'days':
          $time_indicator = 'every';
          $time_unit = 'days';
          $time_length = $count;
          break;

        case 'months':
          $time_indicator = 'every';
          $time_unit = 'months';
          $time_length = $count;
          break;
      }
    }
    // Allow for price formatting with and without HTML.
    if (!$html) {
      if (!$time_length) {
        return $this->stringTranslation->translate('@amount @time_indicator @time_unit', [
          '@amount' => strip_tags($amount),
          '@time_indicator' => $time_indicator,
          '@time_unit' => $time_unit,
        ]);
      }
      return $this->stringTranslation->translate('@amount @time_indicator @time_length @time_unit', [
        '@amount' => strip_tags($amount),
        '@time_indicator' => $time_indicator,
        '@time_length' => $time_length,
        '@time_unit' => $time_unit,
      ]);
    }
    return [
      '#theme' => 'recurly_subscription_price_interval',
      '#amount' => $amount,
      '#time_length' => $time_length,
      '#time_unit' => $time_unit,
      '#time_indicator' => $time_indicator,
    ];
  }

  /**
   * Simple function to print out human-readable transaction status.
   */
  public function formatTransactionStatus($status) {
    switch ($status) {
      case 'success':
        return $this->stringTranslation->translate('Successful payment');

      case 'failed':
        return $this->stringTranslation->translate('Failed payment');

      case 'voided':
        return $this->stringTranslation->translate('Voided');

      case 'declined':
        return $this->stringTranslation->translate('Card declined');

      default:
        return Html::escape($status);
    }
  }

  /**
   * Format a Recurly coupon in a human-readable string.
   *
   * @param \Recurly_Coupon $coupon
   *   The Recurly coupon object being formatted for display.
   * @param string $currency
   *   The currency code in which the coupon is being redeemed.
   * @param bool $html
   *   Whether to return the formatted string with wrapping HTML or not.
   *
   * @return string
   *   The formatted string ready for printing.
   */
  public function formatCoupon(\Recurly_Coupon $coupon, $currency, $html = FALSE) {
    // @todo. I am not sure about Recurly_Coupon. I do not think that class exists.
    if ($coupon->discount_type === 'percent') {
      $amount = Html::escape($coupon->discount_percent) . '%';
    }
    else {
      $coupon_currency = $coupon->discount_in_cents[$currency];
      $amount = $this->formatCurrency($coupon_currency->amount_in_cents, $currency, $html);
    }

    return Html::escape($coupon->name) . $this->stringTranslation->translate('@space(Coupon code: @coupon_code, @amount discount)', ['@space' => ' ', '@coupon_code' => $coupon->coupon_code, '@amount' => $amount]);
  }

}
