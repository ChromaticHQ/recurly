/**
 * @file
 * FormAPI integration with the Recurly forms.
 */
(function ($) {

Drupal.recurly = Drupal.recurly || {};

Drupal.behaviors.recurlyJSSubscribeForm = {
  attach: function (context, settings) {
    // Attaches submission handling to the subscribe form.
    $('#recurlyjs-subscribe-form').once('recurlyjs-subscribe-form', function () {
      $(this).on('submit', Drupal.recurly.recurlyJSTokenFormSubmit);
    });
  }
};

Drupal.behaviors.recurlyJSUpdateBillingForm = {
  attach: function (context, settings) {
    $('#recurlyjs-update-billing-form').once('recurlyjs-update-billing-form', function () {
      $(this).on('submit', Drupal.recurly.recurlyJSTokenFormSubmit);
    });
  }
};

/**
 * Handles submission of the subscribe form.
 */
Drupal.recurly.recurlyJSTokenFormSubmit = function(event) {
  event.preventDefault();

  // Reset the errors display
  $('#recurly-form-errors').html('');
  $('input').removeClass('error');

  // Disable the submit button
  $('button').prop('disabled', true);

  var form = this;
  recurly.token(form, function (err, token) {
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
 */
Drupal.recurly.recurlyJSFormError = function(err) {
  $('button').prop('disabled', false);

  // Add the error class to all form elements that returned an error.
  if (typeof err.fields !== 'undefined') {
    $.each(err.fields, function (index, value) {
      $('input[data-recurly="' + value + '"]').addClass('error');
    });
  }

  // Add the error message to the form within standard Drupal message markup.
  if (typeof err.message !== 'undefined') {
    var messageMarkup = '<div class="messages error">' + err.message + '</div>';
    $('#recurly-form-errors').html(messageMarkup);
  }
};

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
    } else {
      vatNumber.hide();
    }
  }).triggerHandler('init');
})();

})(jQuery);
