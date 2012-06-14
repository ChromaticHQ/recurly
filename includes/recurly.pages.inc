<?php

/**
 * @file
 * Recurly front-end page callbacks.
 */

/**
 * Menu callback; Display a summary of a Recurly account subscriptions.
 */
function recurly_subscription_page($entity_type, $entity) {
  $subscriptions = array();

  // Initialize the Recurly client with the site-wide settings.
  if (!recurly_client_initialize()) {
    return t('Could not initialize the Recurly client.');
  }

  // See if we have a local mapping of entity ID to Recurly account code.
  list($id, $vid, $bundle) = entity_extract_ids($entity_type, $entity);
  $account = recurly_account_load(array('entity_type' => $entity_type, 'entity_id' => $id));

  // If the user does not have an account yet, send them to the signup page.
  if (empty($account)) {
    drupal_goto($entity_type . '/' . $id . '/subscription/signup');
  }

  // Unlikely that we'd have more than 50 subscriptions, but you never know.
  $per_page = 50;
  $subscription_list = Recurly_SubscriptionList::getForAccount($account->account_code, array('per_page' => $per_page));
  $page_subscriptions = recurly_pager_results($subscription_list, $per_page);

  $subscriptions['subscriptions']['#attached']['css'] = array(
    drupal_get_path('module', 'recurly') . '/css/recurly.css',
  );

  foreach ($page_subscriptions as $subscription) {
    // Determine the state of this subscription.
    $states = recurly_subscription_get_states($subscription, $account);

    // Generate the list of links for this subscription.
    $subscription_links = array();
    $subscription_links['update'] = array(
      'href' => $entity_type . '/' . $id . '/subscription/id/' . $subscription->uuid,
      'title' => t('Change plan'),
    );
    if ($subscription->state === 'active') {
      $subscription_links['cancel'] = array(
        'href' => $entity_type . '/' . $id . '/subscription/id/' . $subscription->uuid . '/cancel',
        'title' => t('Cancel'),
      );
    }
    elseif ($subscription->state === 'canceled') {
      $subscription_links['reactivate'] = array(
        'href' => $entity_type . '/' . $id . '/subscription/id/' . $subscription->uuid . '/reactivate',
        'title' => t('Reactivate'),
      );
    }
    // Allow other modules to provide links, perhaps "suspend" for example.
    drupal_alter('recurly_subscription_links', $subscription_links);

    $plan = $subscription->plan;
    $add_ons = array();
    $total = 0;
    foreach ($subscription->subscription_add_ons as $add_on) {
      // Fully load the add on to get the name attribute.
      $full_add_on = Recurly_Addon::get($plan->plan_code, $add_on->add_on_code);
      $add_ons[$add_on->add_on_code] = array(
        'add_on_code' => $add_on->add_on_code,
        'name' => check_plain($full_add_on->name),
        'quantity' => check_plain($add_on->quantity),
        'cost' => recurly_format_currency($add_on->unit_amount_in_cents, $subscription->currency),
      );
      $total += $add_on->unit_amount_in_cents * $add_on->quantity;
    }
    $total += $subscription->unit_amount_in_cents * $subscription->quantity;

    $message = '';
    foreach ($states as $state) {
      $message = recurly_subscription_state_message($state, $account);
      break;
    }

    $subscriptions['subscriptions'][$plan->plan_code] = array(
      '#theme' => 'recurly_subscription_summary',
      '#plan_code' => $plan->plan_code,
      '#plan_name' => check_plain($plan->name),
      '#state_array' => $states,
      '#cost' => recurly_format_currency($subscription->unit_amount_in_cents, $subscription->currency),
      '#quantity' => $subscription->quantity,
      '#add_ons' => $add_ons,
      '#start_date' => recurly_format_date($subscription->activated_at),
      '#current_period_start' => recurly_format_date($subscription->current_period_started_at),
      '#current_period_ends_at' => recurly_format_date($subscription->current_period_ends_at),
      '#total' => recurly_format_currency($total, $subscription->currency),
      '#subscription_links' => theme('links', array('links' => $subscription_links, 'attributes' => array('class' => array('inline', 'links')))),
      '#message' => $message,
    );
  }

  $subscriptions['pager'] = array(
    '#markup' => theme('pager'),
    '#access' => $subscription_list->count() > $per_page,
  );

  drupal_alter('recurly_subscription_page', $subscriptions);
  return $subscriptions;
}

/**
 * Returns a message for a subscription if the subscription state is not active.
 */
function recurly_subscription_state_message($state, $account) {
  switch ($state) {
    case 'active':
      return '';
    case 'closed':
      return t('This account is closed.');
    case 'in_trial':
      return t('Currently in trial period.');
    case 'past_due':
      $url = recurly_url('update_billing', array('account' => $account));
      if ($url) {
        return t('This account is past due. Please <a href="!url">update your billing information</a>.', array('!url' => $url));
      }
      else {
        return t('This account is past due. Please contact an administrator to update your billing information.');
      }
    case 'canceled':
      return t('This subscription is canceled and will not renew. You may reactivate the subscription to by clicking "Reactivate".');
    case 'expired':
      return t('This subscription has expired. Please purchase a new subscription.');
    case 'future':
      return t('This subscription has not started yet. Please contact support if you have any questions.');
    default:
      return '';
  }
}

/**
 * Get a list of all states in which a subscription exists currently.
 *
 * @param $subscription
 *   A Recurly subscription object.
 * @param $account
 *   A Recurly account object.
 */
function recurly_subscription_get_states($subscription, $account) {
  static $past_due = array();
  $states = array();

  // Determine if in a trial.
  if ($subscription->trial_started_at && $subscription->trial_ends_at) {
    $subscription->trial_started_at->setTimezone(new DateTimeZone('UTC'));
    $subscription->trial_ends_at->setTimezone(new DateTimeZone('UTC'));
    $start = $subscription->trial_started_at->format('U');
    $end = $subscription->trial_ends_at->format('U');
    if (REQUEST_TIME > $start && REQUEST_TIME < $end) {
      $states[] = 'in_trial';
    }
  }

  // Determine if non-renewing.
  if (!empty($subscription->total_billing_cycles)) {
    $states[] = 'non_renewing';
  }

  // Retrieve past due subscriptions.
  if (!isset($past_due[$account->account_code])) {
    $subscriptions = Recurly_SubscriptionList::getForAccount($account->account_code, array('state' => 'past_due'));
    $past_due[$account->account_code] = array();
    foreach ($subscriptions as $past_due_subscription) {
      $past_due[$account->account_code][] = $past_due_subscription->uuid;
    }
  }

  if (in_array($subscription->uuid, $past_due[$account->account_code])) {
    $states[] = 'past_due';
  }

  $states[] = $subscription->state;
  return $states;
}

/**
 * Menu callback; Display a list of all invoices for a user.
 */
function recurly_invoices_page($entity_type, $entity) {
  // Initialize the Recurly client with the site-wide settings.
  if (!recurly_client_initialize()) {
    return t('Could not initialize the Recurly client.');
  }

  // See if we have a local mapping of entity ID to Recurly account code.
  list($id, $vid, $bundle) = entity_extract_ids($entity_type, $entity);
  $account = recurly_account_load(array('entity_type' => $entity_type, 'entity_id' => $id));

  $per_page = 20;
  $invoice_list = Recurly_InvoiceList::getForAccount($account->account_code, array('per_page' => $per_page));
  $invoices = recurly_pager_results($invoice_list, $per_page);

  return theme('recurly_invoice_list', array('invoices' => $invoices, 'entity_type' => $entity_type, 'entity' => $entity, 'per_page' => $per_page, 'total' => $invoice_list->count()));
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
    $row[] = l($invoice->invoice_number . $status, $entity_type . '/' . $id . '/subscription/invoices/' . $invoice->invoice_number);
    $row[] = recurly_format_date($invoice->created_at);
    $row[] = recurly_format_currency($invoice->total_in_cents, $invoice->currency);
    $rows[] = array(
      'data' => $row,
      'class' => array(check_plain($invoice->state)),
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

  // See if we have a local mapping of entity ID to Recurly account code.
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

  drupal_set_title(t('Invoice #@number', array('@number' => $invoice->invoice_number)));
  return theme('recurly_invoice', array('invoice' => $invoice, 'invoice_account' => $invoice_account, 'entity_type' => $entity_type, 'entity' => $entity));
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
      'first_name' => check_plain($billing_info->first_name),
      'last_name' => check_plain($billing_info->last_name),
      'address1' => check_plain($billing_info->address1),
      'address2' => isset($billing_info->address2) ? check_plain($billing_info->address2) : NULL,
      'city' => check_plain($billing_info->city),
      'state' => check_plain($billing_info->state),
      'zip' => check_plain($billing_info->zip),
      'country' => check_plain($billing_info->country),
    );
  }
  foreach ($invoice->line_items as $line_item) {
    $variables['line_items'][$line_item->uuid] = array(
      'start_date' => recurly_format_date($line_item->start_date),
      'end_date' => recurly_format_date($line_item->end_date),
      'description' => check_plain($line_item->description),
      'amount' => recurly_format_currency($line_item->total_in_cents, $line_item->currency),
    );
  }
  $transaction_total = 0;
  foreach ($invoice->transactions as $transaction) {
    $transaction_total += $transaction->amount_in_cents;
    $variables['transactions'][$transaction->uuid] = array(
      'date' => recurly_format_date($transaction->created_at),
      'description' => recurly_format_transaction_status($transaction->status),
      'amount' => recurly_format_currency($transaction->amount_in_cents, $transaction->currency),
    );
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

  // See if we have a local mapping of entity ID to Recurly account code.
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

/**
 * Signup page that lists all the available plans for an object.
 */
function recurly_subscription_signup($entity_type, $entity, $currency = NULL) {
  // Initialize the Recurly client with the site-wide settings.
  if (!recurly_client_initialize()) {
    return t('Could not initialize the Recurly client.');
  }

  $currency = isset($currency) ? $currency : variable_get('recurly_default_currency', 'USD');
  $all_plans = recurly_subscription_plans();
  $enabled_plan_keys = variable_get('recurly_subscription_plans', array());
  $enabled_plans = array();
  foreach ($all_plans as $plan) {
    if (in_array($plan->plan_code, $enabled_plan_keys)) {
      $enabled_plans[$plan->plan_code] = $plan;
    }
  }
  return theme('recurly_subscription_signup', array('plans' => $enabled_plans, 'entity_type' => $entity_type, 'entity' => $entity, 'currency' => $currency));
}

/**
 * Preprocess variables for the presentation of the signup page.
 */
function template_preprocess_recurly_subscription_signup(&$variables) {
  $plans = $variables['plans'];
  $currency = $variables['currency'];
  $variables['filtered_plans'] = array();
  foreach ($plans as $plan_code => $plan) {
    $setup_fee_amount = NULL;
    foreach ($plan->setup_fee_in_cents as $setup_currency) {
      if ($setup_currency->currencyCode === $currency) {
        $setup_fee_amount = recurly_format_currency($setup_currency->amount_in_cents, $setup_currency->currencyCode, TRUE);
        break;
      }
    }
    $unit_amount = NULL;
    foreach ($plan->unit_amount_in_cents as $unit_currency) {
      if ($unit_currency->currencyCode === $currency) {
        $unit_amount = recurly_format_currency($unit_currency->amount_in_cents, $unit_currency->currencyCode, TRUE);
        break;
      }
    }
    $variables['filtered_plans'][$plan_code] = array(
      'plan_code' => check_plain($plan_code),
      'name' => check_plain($plan->name),
      'description' => check_plain($plan->description),
      'setup_fee' => $setup_fee_amount,
      'amount' => $unit_amount,
      'plan_interval' => recurly_format_price_interval($unit_amount, $plan->plan_interval_length, $plan->plan_interval_unit, TRUE),
      'trial_interval' => $plan->trial_interval_length ? recurly_format_price_interval(NULL, $plan->trial_interval_length, $plan->trial_interval_unit, TRUE) : NULL,
      'signup_url' => recurly_url('subscribe', array('entity_type' => $variables['entity_type'], 'entity' => $variables['entity'], 'plan_code' => $plan_code, 'currency' => $currency)),
    );
  }
}

/**
 * Utility function to retrieve a specific page of results from a Recurly_Pager.
 *
 * @param $pager_object
 *   Any object that extends a Recurly_Pager object, such as a
 *   Recurly_InvoiceList, Recurly_SubscriptionList, or Recurly_TransactionList.
 * @param $element
 *   A unique identifier for this pager.
 * @param $per_page
 *   The number of items to display per page.
 * @param $page_num
 *   The desired page number to display. Usually automatically determined from
 *   the URL.
 */
function recurly_pager_results($pager_object, $per_page, $page_num = NULL) {
  if (!isset($page_num)) {
    $page_num = isset($_GET['page']) ? (int) $_GET['page'] : 0;
  }

  // Fast forward the list to the current page.
  $start = $page_num * $per_page;
  for ($n = 0; $n < $start; $n++) {
    $pager_object->next();
  }

  // Populate $page_results with the current page.
  $total = $pager_object->count();
  $page_end = min($start + $per_page, $total);
  $page_results = array();
  for ($n = $start; $n < $page_end; $n++) {
    $invoice = $pager_object->current();
    $page_results[$invoice->invoice_number] = $invoice;
    $pager_object->next();
  }

  pager_default_initialize($total, $per_page);

  return $page_results;
}
