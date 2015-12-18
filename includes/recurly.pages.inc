<?php

/**
 * @file
 * Recurly front-end page callbacks.
 */

/**
 * Menu callback; Redirects a Recurly account code subscription management page.
 */
function recurly_subscription_redirect($account_code) {
  $account = recurly_account_load(array('account_code' => $account_code), TRUE);
  if ($account) {
    drupal_goto($account->entity_type . '/' . $account->entity_id . '/subscription');
  }
  else {
    return MENU_NOT_FOUND;
  }
}

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
 * Menu callback; Display a list of all invoices for a user.
 */
function recurly_invoices_page($entity_type, $entity) {
  // Initialize the Recurly client with the site-wide settings.
  if (!recurly_client_initialize()) {
    return t('Could not initialize the Recurly client.');
  }

  // Load the account information. This should already be cached by the access
  // check to this page by recurly_subscription_page_access().
  list($id, $vid, $bundle) = entity_extract_ids($entity_type, $entity);
  $account = recurly_account_load(array('entity_type' => $entity_type, 'entity_id' => $id));

  $per_page = 20;
  $invoice_list = Recurly_InvoiceList::getForAccount($account->account_code, array('per_page' => $per_page));
  $invoices = recurly_pager_results($invoice_list, $per_page);

  // @FIXME
// theme() has been renamed to _theme() and should NEVER be called directly.
// Calling _theme() directly can alter the expected output and potentially
// introduce security issues (see https://www.drupal.org/node/2195739). You
// should use renderable arrays instead.
//
//
// @see https://www.drupal.org/node/2195739
// return theme('recurly_invoice_list',
//     array(
//       'invoices' => $invoices,
//       'entity_type' => $entity_type,
//       'entity' => $entity,
//       'per_page' => $per_page,
//       'total' => $invoice_list->count(),
//     ));

}

/**
 * Preprocess variables for the recurly-invoice-list.tpl.php file.
 */
function template_preprocess_recurly_invoice_list(&$variables) {
  $invoices = $variables['invoices'];
  $entity_type = $variables['entity_type'];
  $entity = $variables['entity'];
  list($id, $vid, $bundle) = entity_extract_ids($entity_type, $entity);

  $header = array(t('Number'), t('Date'), t('Total'));
  $rows = array();
  foreach ($invoices as $invoice) {
    $status = '';
    if ($invoice->state === 'past_due') {
      $status = ' (' . t('Past due') . ')';
    }
    elseif ($invoice->state === 'failed') {
      $status = ' (' . t('Failed') . ')';
    }

    $row = array();
    // @FIXME
// l() expects a Url object, created from a route name or external URI.
// $row[] = l($invoice->invoice_number . $status, $entity_type . '/' . $id . '/subscription/invoices/' . $invoice->invoice_number);

    $row[] = recurly_format_date($invoice->created_at);
    $row[] = recurly_format_currency($invoice->total_in_cents, $invoice->currency);
    $rows[] = array(
      'data' => $row,
      'class' => array(\Drupal\Component\Utility\SafeMarkup::checkPlain($invoice->state)),
    );
  }

  $variables['table'] = array(
    'header' => $header,
    'rows' => $rows,
    'attributes' => array('class' => array('invoice-list')),
    'sticky' => FALSE,
  );
}

/**
 * Menu callback; Display an individual invoice.
 */
function recurly_invoice_page($entity_type, $entity, $invoice_number) {
  // Initialize the Recurly client with the site-wide settings.
  if (!recurly_client_initialize()) {
    return t('Could not initialize the Recurly client.');
  }

  // Load the account information. This should already be cached by the access
  // check to this page by recurly_subscription_page_access().
  list($id, $vid, $bundle) = entity_extract_ids($entity_type, $entity);
  $account = recurly_account_load(array('entity_type' => $entity_type, 'entity_id' => $id));

  // Load the invoice.
  try {
    $invoice = Recurly_Invoice::get($invoice_number);
  }
  catch (Recurly_NotFoundError $e) {
    drupal_set_message(t('Invoice not found'));
    return MENU_NOT_FOUND;
  }

  // Load the invoice account.
  $invoice_account = $invoice->account->get();

  // Ensure that the user account is the same as the invoice account.
  if (empty($account) || $invoice_account->account_code !== $account->account_code) {
    return MENU_NOT_FOUND;
  }

  // @FIXME
// drupal_set_title() has been removed. There are now a few ways to set the title
// dynamically, depending on the situation.
//
//
// @see https://www.drupal.org/node/2067859
// drupal_set_title(t('Invoice #@number', array('@number' => $invoice->invoice_number)));

  // @FIXME
// theme() has been renamed to _theme() and should NEVER be called directly.
// Calling _theme() directly can alter the expected output and potentially
// introduce security issues (see https://www.drupal.org/node/2195739). You
// should use renderable arrays instead.
//
//
// @see https://www.drupal.org/node/2195739
// return theme('recurly_invoice',
//     array(
//       'invoice' => $invoice,
//       'invoice_account' => $invoice_account,
//       'entity_type' => $entity_type,
//       'entity' => $entity,
//     ));

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

/**
 * Preprocess variables for invoice pages.
 */
function template_preprocess_recurly_invoice(&$variables) {
  $invoice = $variables['invoice'];
  $invoice_account = $variables['invoice_account'];
  $billing_info = isset($invoice->billing_info) ? $invoice->billing_info->get() : NULL;
  list($id, $vid, $bundle) = entity_extract_ids($variables['entity_type'], $variables['entity']);

  $due_amount = $invoice->state !== 'collected' ? $invoice->total_in_cents : 0;
  $paid_amount = $invoice->state === 'collected' ? $invoice->total_in_cents : 0;
  $variables += array(
    'invoice_date' => recurly_format_date($invoice->created_at),
    'pdf_path' => $variables['entity_type'] . '/' . $id . '/subscription/invoices/' . $invoice->invoice_number . '/pdf',
    'subtotal' => recurly_format_currency($invoice->subtotal_in_cents, $invoice->currency),
    'total' => recurly_format_currency($invoice->total_in_cents, $invoice->currency),
    'due' => recurly_format_currency($due_amount, $invoice->currency),
    'paid' => recurly_format_currency($paid_amount, $invoice->currency),
    'billing_info' => isset($billing_info),
    'line_items' => array(),
    'transactions' => array(),
  );
  if ($billing_info) {
    $variables += array(
      'first_name' => \Drupal\Component\Utility\SafeMarkup::checkPlain($billing_info->first_name),
      'last_name' => \Drupal\Component\Utility\SafeMarkup::checkPlain($billing_info->last_name),
      'address1' => \Drupal\Component\Utility\SafeMarkup::checkPlain($billing_info->address1),
      'address2' => isset($billing_info->address2) ? \Drupal\Component\Utility\SafeMarkup::checkPlain($billing_info->address2) : NULL,
      'city' => \Drupal\Component\Utility\SafeMarkup::checkPlain($billing_info->city),
      'state' => \Drupal\Component\Utility\SafeMarkup::checkPlain($billing_info->state),
      'zip' => \Drupal\Component\Utility\SafeMarkup::checkPlain($billing_info->zip),
      'country' => \Drupal\Component\Utility\SafeMarkup::checkPlain($billing_info->country),
    );
  }
  foreach ($invoice->line_items as $line_item) {
    $variables['line_items'][$line_item->uuid] = array(
      'start_date' => recurly_format_date($line_item->start_date),
      'end_date' => recurly_format_date($line_item->end_date),
      'description' => \Drupal\Component\Utility\SafeMarkup::checkPlain($line_item->description),
      'amount' => recurly_format_currency($line_item->total_in_cents, $line_item->currency),
    );
  }
  $transaction_total = 0;
  foreach ($invoice->transactions as $transaction) {
    $variables['transactions'][$transaction->uuid] = array(
      'date' => recurly_format_date($transaction->created_at),
      'description' => recurly_format_transaction_status($transaction->status),
      'amount' => recurly_format_currency($transaction->amount_in_cents, $transaction->currency),
    );
    if ($transaction->status == 'success') {
      $transaction_total += $transaction->amount_in_cents;
    }
    else {
      $variables['transactions'][$transaction->uuid]['amount'] = '(' . $variables['transactions'][$transaction->uuid]['amount'] . ')';
    }
  }
  $variables['transactions_total'] = recurly_format_currency($transaction_total, $invoice->currency);

}

/**
 * Deliver an invoice PDF file from Recurly.com.
 */
function recurly_invoice_pdf($entity_type, $entity, $invoice_number) {
  // Initialize the Recurly client with the site-wide settings.
  if (!recurly_client_initialize()) {
    return t('Could not initialize the Recurly client.');
  }

  // Load the account information. This should already be cached by the access
  // check to this page by recurly_subscription_page_access().
  list($id, $vid, $bundle) = entity_extract_ids($entity_type, $entity);
  $account = recurly_account_load(array('entity_type' => $entity_type, 'entity_id' => $id));

  // Load the invoice.
  try {
    $invoice = Recurly_Invoice::get($invoice_number);
    $pdf = Recurly_Invoice::getInvoicePdf($invoice_number);
  }
  catch (Recurly_NotFoundError $e) {
    drupal_set_message(t('Invoice not found'));
    return MENU_NOT_FOUND;
  }

  // Load the invoice account.
  $invoice_account = $invoice->account->get();

  // Ensure that the user account is the same as the invoice account.
  if (empty($account) || $invoice_account->account_code !== $account->account_code) {
    return MENU_NOT_FOUND;
  }

  if (!empty($pdf)) {
    if (headers_sent()) {
      die("Unable to stream pdf: headers already sent");
    }

    drupal_add_http_header('Content-Type', 'application/pdf', TRUE);
    drupal_add_http_header('Content-Disposition', 'inline; filename="' . $invoice_number . '.pdf"', TRUE);
    drupal_send_headers();

    // I guess below is not necessary plus filesize was not working anyway?!
    // drupal_add_http_header('Content-Transfer-Encoding', 'binary', TRUE);
    // drupal_add_http_header('Content-Length', filesize($pdf), TRUE);
    print $pdf;
    drupal_exit();
  }
}
