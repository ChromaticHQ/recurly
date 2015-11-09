/**
 * @file
 * FormAPI integration with the Recurly forms.
 */
(function ($) {
  // On form submit, we stop submission to go get the token
  $('form').on('submit', function (event) {
    // Prevent the form from submitting while we retrieve the token from Recurly
    event.preventDefault();

    // Reset the errors display
    $('#errors').text('');
    $('input').removeClass('error');

    // Disable the submit button
    $('button').prop('disabled', true);

    var form = this;
    recurly.token(this, function (err, token) {
      if (err) {
        error(err);
      }
      else {
        form.submit();
      }
    });
  });

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

  function error (err) {
    console && console.error(err);
    $('#errors').text(err.message);
    $('button').prop('disabled', false);
  }

})(jQuery);
