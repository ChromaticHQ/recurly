<?php

/**
 * @file
 * Contains \Drupal\recurly\RecurlyFormatManager.
 */

namespace Drupal\recurly;

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

class RecurlyFormatManager {

  /**
   * Format a Recurly subscription state.
   */
  function formatState($state) {
    switch ($state) {
      case RECURLY_STATE_ACTIVE:
        return t('Active');

      case RECURLY_STATE_CANCELED:
        return t('Canceled (will not renew)');

      case RECURLY_STATE_EXPIRED:
        return t('Expired');

      case RECURLY_STATE_FUTURE:
        return t('Future Activation');

      case RECURLY_STATE_PENDING_SUBSCRIPTION:
        return t('Switching to new plan');

      case RECURLY_STATE_IN_TRIAL:
        return t('Trial');

      case RECURLY_STATE_LIVE:
        return t('Live');

      case RECURLY_STATE_PAST_DUE:
        return t('Past Due');
    }
  }


  /**
   * Format a date for use in invoices.
   */
  function formatDate($date) {
    $format = \Drupal::config('recurly.settings')->get('recurly_date_format');
    if (is_object($date)) {
      $date->setTimezone(new DateTimeZone('UTC'));
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
  function formatCurrency($price_in_cents, $currency, $html = FALSE) {
    $currencies = recurly_currency_list();
    $currency_info = isset($currencies[$currency]) ? $currencies[$currency] : ['', ' ' . $currency];
    $prefix = $currency_info[0] ? $currency_info[0] : '';
    $suffix = $currency_info[1] ? $currency_info[1] : '';
    $thousands_separator = isset($currency_info[2]) ? $currency_info[2] : ',';
    $decimal_separator = isset($currency_info[3]) ? $currency_info[3] : '.';
    $decimals = isset($currency_info[4]) ? $currency_info[4] : 2;
    $rounding_step = isset($currency_info[5]) ? $currency_info[5] : NULL;

    // Commerce module provides a more flexible and complete currency formatter.
    if (\Drupal::moduleHandler()->moduleExists('commerce')) {
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
      preg_match('/([^0-9]*)?([0-9' . preg_quote($thousands_separator) . '])([0-9' . preg_quote($decimal_separator) . '])(.*)?/', $formatted, $amount_array);
      if ($amount_array[1]) {
        $amount_string .= '<span class="currency-prefix">' . $amount_array[1] . '</span>';
      }
      if ($amount_array[2]) {
        $amount_string .= '<span class="currency-dollars">' . $amount_array[2] . '</span>';
      }
      if ($amount_array[3]) {
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
  function formatPriceInterval($amount, $count, $unit, $html = FALSE) {
    if ($amount === NULL) {
      if ($unit == 'days') {
        return \Drupal::translation()->formatPlural($count, '1 day trial', '@count day trial');
      }
      else {
        return \Drupal::translation()->formatPlural($count, '1 month trial', '@count month trial');
      }
    }

    $replacements = [
      '!count' => $html ? '<span class="plan-count">' . SafeMarkup::checkPlain($count) . '<span>' : SafeMarkup::checkPlain($count),
      '!amount' => $html ? '<span class="plan-amount">' . $amount . '</span>' : $amount,
    ];
    if ($count == 1) {
      switch ($unit) {
        case 'days':
          return t('!amount per day', $replacements);

        case 'months':
          return t('!amount per month', $replacements);
      }
    }
    elseif ($count == 7 && $unit == 'days') {
      return t('!amount per week', $replacements);
    }
    elseif ($count == 12 && $unit == 'months') {
      return t('!amount per year', $replacements);
    }
    else {
      switch ($unit) {
        case 'days':
          return t('!amount every !count days', $replacements);

        case 'months':
          return t('!amount every !count months', $replacements);
      }
    }
  }


  /**
   * Simple function to print out human-readable transaction status.
   */
  function formatTransactionStatus($status) {
    switch ($status) {
      case 'success':
        return t('Successful payment');

      case 'failed':
        return t('Failed payment');

      case 'voided':
        return t('Voided');

      case 'declined':
        return t('Card declined');

      default:
        return SafeMarkup::checkPlain($status);
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
  function formatCoupon(\Recurly_Coupon $coupon, $currency, $html = FALSE) {
    // @todo. I am not sure about Recurly_Coupon. I do not think that class exists.
    if ($coupon->discount_type === 'percent') {
      $amount = SafeMarkup::checkPlain($coupon->discount_percent) . '%';
    }
    else {
      $coupon_currency = $coupon->discount_in_cents[$currency];
      $amount = $this->formatCurrency($coupon_currency->amount_in_cents, $currency, $html);
    }

    return SafeMarkup::checkPlain($coupon->name) . ' (' . t('@amount discount', ['@amount' => $amount]) . ')';
  }
}
