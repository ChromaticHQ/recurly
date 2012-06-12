/**
 * @file
 * FormAPI integration with the Recurly forms.
 */
(function ($) {

Drupal.recurly = Drupal.recurly || {};
Drupal.recurly.beforeInject = function(form) {
  // Imitate typical Drupal element styling.
  $(form).find('input[type=text]').addClass('form-text').parent().addClass('form-item');
  $(form).find('select').addClass('form-select').parent().addClass('form-item');

   // Remove the submit button. This will be triggered by the form submit.
  $(form).find('.footer').remove();
};

Drupal.recurly.afterInject = function(form) {
  // Nested form tags cause issues in IE, except if there are two of them.
  // See http://anderwald.info/internet/nesting-form-tags-in-xhtml/
  $(form).before('<form class="dummy-form" action="#" style="display: none"></form>');

  $(form).parents('form:first').submit(function(e) {
    if ($(this).find('input.recurly-token').val().length === 0) {
      e.preventDefault();
      $(form).triggerHandler('submit');
    }
  });
};

Drupal.recurly.successHandler = function(responseToken) {
  $('.recurly-form-wrapper').find('input.recurly-token').val(responseToken);
  $('.recurly-form-wrapper').parents('form:first').submit();
};

})(jQuery);
