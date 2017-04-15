/**
 * @file
 * FormAPI integration with the Recurly forms.
 */

(function ($) {

  'use strict';

  Drupal.recurly = Drupal.recurly || {};

  Drupal.behaviors.recurlyJsFormBase = {
    attach: function (context, settings) {
      recurly.on('change', Drupal.recurly.recurlyJSPaymentMethod);
    }
  };

  Drupal.behaviors.recurlyJSSubscribeForm = {
    attach: function (context, settings) {
      // Attaches submission handling to the subscribe form.
      $('#recurlyjs-subscribe').once('recurlyjs-subscribe').each(function () {
        $(this).on('submit', Drupal.recurly.recurlyJSTokenFormSubmit);
      });
    }
  };

  Drupal.behaviors.recurlyJSUpdateBillingForm = {
    attach: function (context, settings) {
      // Attaches submission handling to the update billing form.
      $('#recurlyjs-update-billing').once('recurlyjs-update-billing').each(function () {
        $(this).on('submit', Drupal.recurly.recurlyJSTokenFormSubmit);
      });
    }
  };

  /**
   * Handles submission of the subscribe form.
   *
   * @param {object} event A jQuery event object.
   */
  Drupal.recurly.recurlyJSTokenFormSubmit = function (event) {
    event.preventDefault();

    // Reset the errors display.
    Drupal.recurly.clearErrors();
    $('.recurlyjs-form-item__error').removeClass('.recurlyjs-form-item__error');

    // Disable the submit button.
    $('button').prop('disabled', true);

    var form = this;
    recurly.token(form, function (err, token) { // eslint-disable-line.
      if (err) {
        Drupal.recurly.recurlyJSFormError(err);
      }
      else {
        form.submit();
      }
    });
  };

  /**
   * Handles form errors.
   *
   * @param {object} err An error object.
   */
  Drupal.recurly.recurlyJSFormError = function (err) {
    var errorFields = {
      first_name: Drupal.t('First name'),
      last_name: Drupal.t('Last name'),
      email: Drupal.t('Email address'),
      number: Drupal.t('Credit Card number'),
      postal_code: Drupal.t('Postal Code'),
      month: Drupal.t('Expiration Month'),
      year: Drupal.t('Expiration Year')
    };

    $('button').prop('disabled', false);

    var fieldErrorsList = $.map(err.fields, function (field) {
      // Add a class to each element with an error.
      $('.recurlyjs-form-item__' + field).addClass('recurlyjs-form-item__error');
      // Append a message to the list.
      return '<li>' + errorFields[field] + '</li>';
    }).join('');

    // Add the error class to all form elements that returned an error.
    if (typeof err.fields !== 'undefined') {
      $.each(err.fields, function (index, value) {
        $('input[data-recurly="' + value + '"]').addClass('error');
      });
    }

    // Add the error message to the form within standard Drupal message markup.
    if (typeof err.message !== 'undefined') {
      var messageMarkup = '<div class="messages error">' + err.message + '<ul>' + fieldErrorsList + '</ul></div>';
      $('#recurly-form-errors').html(messageMarkup);
    }
  };

  Drupal.recurly.clearErrors = function () {
    $('#recurly-form-errors').html('');
  };

  /**
   * Update icon used in credit card number field when value of field changes.
   *
   * @param {object} state
   *   See https://dev.recurly.com/docs/events
   */
  Drupal.recurly.recurlyJSPaymentMethod = function (state) {
    if (state.fields.number.focus === true) {
      $('.recurlyjs-icon-card').removeClass().addClass('recurlyjs-icon-card recurlyjs-icon-card__' + state.fields.number.brand);
    }
  };

  /**
   * Update order summary when pricing information changes.
   *
   * @param {object} pricingState
   *   See https://dev.recurly.com/docs/pricing
   */
  Drupal.recurly.recurlyJSPricing = function (pricingState) {
    Drupal.recurly.clearErrors();

    var displaySubtotal = false;
    // Toggle visibility of order summary elements that may or may not be used
    // depending on the plan.
    // Toggle visiblity of setup fee.
    if (parseInt(pricingState.now.setup_fee) > 0) {
      $('.recurlyjs-setup-fee').removeClass('recurlyjs-element__hidden');
      displaySubtotal = true;
    }
    else {
      $('.recurlyjs-setup-fee').addClass('recurlyjs-element__hidden');
    }

    // Toggle visibility of discount.
    if (parseInt(pricingState.now.discount) > 0) {
      $('.recurlyjs-discount').removeClass('recurlyjs-element__hidden');
      displaySubtotal = true;
    }
    else {
      $('.recurlyjs-discount').addClass('recurlyjs-element__hidden');
    }

    // Toggle visibility of sub total. No need to display sub-total if none of the
    // above are set.
    if (displaySubtotal && parseInt(pricingState.now.subtotal) > 0) {
      $('.recurlyjs-subtotal').removeClass('recurlyjs-element__hidden');
    }
    else {
      $('.recurlyjs-subtotal').addClass('recurlyjs-element__hidden');
    }

    // Toggle visibility of taxes.
    if (parseInt(pricingState.now.tax) > 0) {
      $('.recurlyjs-tax').removeClass('recurlyjs-element__hidden');
    }
    else {
      $('.recurlyjs-tax').addClass('recurlyjs-element__hidden');
    }
  };

  // VAT is only needed for EU countries.
  (function () {
    var country = $('#country');
    var vatNumber = $('#vat-number');
    var euCountries = [
      'AT', 'BE', 'BG', 'CY', 'CZ', 'DK', 'EE', 'ES', 'FI', 'FR',
      'DE', 'GB', 'GR', 'HU', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT',
      'NL', 'PL', 'PT', 'RO', 'SE', 'SI', 'SK', 'HR'
    ];

    country.on('change init', function (event) {
      if (~euCountries.indexOf(this.value)) {
        vatNumber.show();
      }
      else {
        vatNumber.hide();
      }
    }).triggerHandler('init');
  })();

})(jQuery);
