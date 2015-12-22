<?php

/**
 * @file
 * Recurly front-end page callbacks.
 */

/**
 * Menu callback; Reactivate a subscription immediately and redirect.
 */
function recurly_subscription_reactivate($entity_type, $entity, $subscription_id) {
  // Initialize the Recurly client with the site-wide settings.
  if (!recurly_client_initialize()) {
    return t('Could not initialize the Recurly client.');
  }

  // Load the subscription.
  if ($subscription_id === 'latest') {
    list($id, $vid, $bundle) = entity_extract_ids($entity_type, $entity);
    $local_account = recurly_account_load(array('entity_type' => $entity_type, 'entity_id' => $id), TRUE);
    $subscriptions = recurly_account_get_subscriptions($local_account->account_code, 'active');
    $subscription = reset($subscriptions);
  }
  else {
    try {
      $subscription = Recurly_Subscription::get($subscription_id);
    }
    catch (Recurly_NotFoundError $e) {
      drupal_set_message(t('Subscription not found'));
      return MENU_NOT_FOUND;
    }
  }

  list($id, $vid, $bundle) = entity_extract_ids($entity_type, $entity);

  try {
    $subscription->reactivate();
    drupal_set_message(t('Plan @plan reactivated! Normal billing will resume on @date.', array('@plan' => $subscription->plan->name, '@date' => recurly_format_date($subscription->current_period_ends_at))));
  }
  catch (Recurly_Error $e) {
    drupal_set_message(t('The plan could not be reactivated because the billing service encountered an error.'));
    return;
  }

  drupal_goto($entity_type . '/' . $id . '/subscription');
}

/**
 * Menu callback; Display form for redeeming a coupon.
 */
function recurly_subscription_redeem_coupon($form, &$form_state, $entity_type, $entity) {
  $form['#entity_type'] = $entity_type;
  $form['#entity'] = $entity;
  list($id, $vid, $bundle) = entity_extract_ids($entity_type, $entity);

  // Check the user's account for any current coupons.
  $account = recurly_account_load(array('entity_type' => $form['#entity_type'], 'entity_id' => $id));
  $confirming_replacement_coupon = isset($form_state['confirm']) && isset($form_state['coupon']) && isset($form_state['existing_coupon']);
  $form_state['account'] = $account;

  // The output of this form varies based on if the user has an existing coupon,
  // and if they need to confirm replacing their coupon with a new one.
  if ($confirming_replacement_coupon) {
    $form_state['confirmed'] = TRUE;
    $help = '<p>' . t('Your account already has a coupon that will be applied to your next invoice. Are you sure you want to replace your existing coupon "!old_coupon" with "!new_coupon"? You may not be able to use your previous coupon again.',
      array(
        '!old_coupon' => recurly_format_coupon($form_state['existing_coupon'], $form_state['existing_redemption']->currency),
        '!new_coupon' => recurly_format_coupon($form_state['coupon'], $form_state['values']['coupon_currency']))) . '</p>';
  }
  elseif ($account->redemption) {
    $form_state['existing_redemption'] = $account->redemption->get();
    $form_state['existing_coupon'] = $form_state['existing_redemption']->coupon->get();

    $help = '<p>' . t('Your next invoice will have the following coupon applied:') . ' <strong>' . recurly_format_coupon($form_state['existing_coupon'], $form_state['existing_redemption']->currency) . '</strong></p>';
    $help .= '<p>' . t('Please note that only one coupon can be redeemed per invoice.') . '</p>';
  }
  else {
    $help = '<p>' . t('Enter a coupon code below and it will be applied to your next invoice.') . '</p>';
  }

  $form['help'] = array(
    '#markup' => $help,
  );
  $form['coupon_code'] = array(
    '#type' => 'textfield',
    '#title' => t('Coupon code'),
    '#required' => TRUE,
    '#default_value' => isset($form_state['coupon']) ? $form_state['coupon']->coupon_code : '',
    '#access' => !$confirming_replacement_coupon,
    '#size' => 20,
  );
  $form['coupon_currency'] = array(
    '#type' => 'select',
    '#title' => t('Coupon currency'),
    '#options' => array_combine(array_keys(recurly_currency_list()), array_keys(recurly_currency_list())),
    '#default_value' => \Drupal::config('recurly.settings')->get('recurly_default_currency'),
    '#description' => t('If your coupon specifies a currency, select it here. Not all coupons work in all currencies.'),
    '#access' => !$confirming_replacement_coupon,
  );
  $form['actions'] = array(
    '#type' => 'actions',
  );
  $form['actions']['submit'] = array(
    '#type' => 'submit',
    '#value' => $confirming_replacement_coupon ? t('Replace previous coupon') : t('Redeem coupon'),
  );
  $form['actions']['cancel'] = array(
    '#type' => 'markup',
    '#markup' => '<a href="' . recurly_url('redeem_coupon', array('entity_type' => $entity_type, 'entity' => $entity)) . '">' . t('Cancel') . '</a>',
    '#access' => $confirming_replacement_coupon,
  );
  return $form;
}

/**
 * Validation handler for recurly_subscription_redeem_coupon().
 */
function recurly_subscription_redeem_coupon_validate($form, &$form_state) {
  // Query Recurly to make sure this is a valid coupon code.
  try {
    $coupon = Recurly_Coupon::get($form_state['values']['coupon_code']);
    $form_state['coupon'] = $coupon;
  }
  catch (Recurly_NotFoundError $e) {
    form_set_error('coupon_code', t('The coupon code you have entered is not valid.'));
    return;
  }

  // Check that the coupon is available in the specified currency.
  if ($form_state['coupon'] && $form_state['coupon']->discount_type !== 'percent') {
    if (!$form_state['coupon']->discount_in_cents->offsetExists($form_state['values']['coupon_currency'])) {
      form_set_error('coupon_currency', t('The coupon code you have entered is not valid in @currency currency.', array('@currency' => $form_state['values']['coupon_currency'])));
      return;
    }
  }
}

/**
 * Submit handler for recurly_subscription_redeem_coupon().
 */
function recurly_subscription_redeem_coupon_submit($form, &$form_state) {
  $coupon = $form_state['coupon'];
  $account = $form_state['account'];

  if ($account && $coupon && $account->redemption) {
    // If the user already has a coupon, rebuild the form and ask confirmation.
    if (empty($form_state['confirmed'])) {
      drupal_set_message('You already have an active coupon, are you sure you want to replace it?', 'warning');
      $form_state['confirm'] = TRUE;
      $form_state['rebuild'] = TRUE;
      return;
    }
    // If confirmed, delete the existing coupon before redeeming the new one.
    else {
      $form_state['existing_redemption']->delete();
    }
  }

  // Now redeem the new coupon.
  $response = $coupon->redeemCoupon($account->account_code, $form_state['values']['coupon_currency']);

  // If the response is NULL that means for one reason or another the coupon
  // could not be applied. This is most likely because the code has already
  // reached the maximum number of redemptions or has expired.
  if (is_null($response)) {
    drupal_set_message(t('Unable to redeem the coupon @code, the coupon may no longer be valid.', array('@code' => $coupon->coupon_code)), 'error');
  }
  else {
    drupal_set_message(t('The coupon !coupon has been applied to your account and will be redeemed the next time your subscription renews.', array('!coupon' => recurly_format_coupon($coupon, $form_state['values']['coupon_currency']))));
  }
}
