/**
 * @file
 * Configure recurly.js.
 */

recurly.configure({
  publicKey: drupalSettings.recurlyjs.recurly_public_key,
  style: {
    number: {
      placeholder: 'xxxx xxxx xxxx xxxx'
    },
    cvv: {
      placeholder: '123'
    },
    month: {
      placeholder: 'MM'
    },
    year: {
      placeholder: 'YYYY'
    }
  }
});
