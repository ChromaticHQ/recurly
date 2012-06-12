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

  // Unlikely that we'd have more than 50 subscriptions, but you never know.
  $per_page = 50;
  $subscription_list = Recurly_SubscriptionList::getForAccount($account->account_code, array('per_page' => $per_page));
  $page_subscriptions = recurly_pager_results($subscription_list, $per_page);

  $subscriptions['subscriptions']['#attached']['css'] = array(
    drupal_get_path('module', 'recurly') . '/css/recurly-subscription.css',
  );
  foreach ($page_subscriptions as $subscription) {
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
    $subscriptions['subscriptions'][$plan->plan_code] = array(
      '#theme' => 'recurly_subscription_summary',
      '#plan_code' => $plan->plan_code,
      '#plan_name' => check_plain($plan->name),
      '#state' => $subscription->state,
      '#cost' => recurly_format_currency($subscription->unit_amount_in_cents, $subscription->currency),
      '#quantity' => $subscription->quantity,
      '#add_ons' => $add_ons,
      '#start_date' => recurly_format_date($subscription->activated_at),
      '#current_period_start' => recurly_format_date($subscription->current_period_started_at),
      '#current_period_ends_at' => recurly_format_date($subscription->current_period_ends_at),
      '#total' => recurly_format_currency($total, $subscription->currency),
      '#subscription_links' => theme('links', array('links' => $subscription_links)),
      '#message' => recurly_subscription_state_message($subscription->state),
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
function recurly_subscription_state_message($state) {
  switch ($state) {
    case 'active':
      return '';
    case 'closed':
      return t('Your previous account is now closed. Please repurchase a subscription.');
    case 'in_trial':
      return t('You are currently in a trial period.');
    case 'past_due':
      return t('Your account is past due. Please update your billing information');
    case 'canceled':
      return t('Your previous subscription was canceled. You can reactivate your previous subscription to by clicking Reactivate');
    case 'expired':
      return t('Your previous subscription has expired. Please purchase a new subscription.');
    case 'future':
      return t('Your subscription has not started yet. Please contact support if you have any questions.');
    default:
      return '';
  }
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

  $per_page = 5;
  $invoice_list = Recurly_InvoiceList::getForAccount($account->account_code, array('per_page' => $per_page));
  $page_invoices = recurly_pager_results($invoice_list, $per_page);

  $header = array(t('Number'), t('Date'), t('Total'));
  $rows = array();
  foreach ($page_invoices as $invoice) {
    $row = array();
    $row[] = l($invoice->invoice_number, $entity_type . '/' . $id . '/subscription/invoices/' . $invoice->invoice_number);
    $row[] = recurly_format_date($invoice->created_at);
    $row[] = recurly_format_currency($invoice->total_in_cents, $invoice->currency);
    $rows[] = $row;
  }

  $invoices['table']['#attached']['css'] = array(
    drupal_get_path('module', 'recurly') . '/css/recurly-subscription.css',
  );
  $invoices['table'] = array(
    '#markup' => theme('table', array('header' => $header, 'rows' => $rows)),
  );
  $invoices['pager'] = array(
    '#markup' => theme('pager'),
    '#access' => $invoice_list->count() > $per_page,
  );

  return $invoices;
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

  // Load the invoice. For some reason the RecurlySDK returns this as a list,
  // so a little clean up is necessary.
  $invoice = Recurly_Invoice::get($invoice_number);

  // Load the invoice account.
  $invoice_account = $invoice->account->get();

  // Ensure that the user account is the same as the invoice account.
  if ($invoice_account->account_code !== $account->account_code) {
    return MENU_ACCESS_DENIED;
  }

  return 'test';//drupal_render_children($invoice);
}

/**
 * Deliver an invoice PDF file from Recurly.com.
 */
function recurly_invoice_pdf($entity_type, $entity, $invoice_number) {
  if (empty($invoice_number) || !is_numeric($uid) || !is_numeric($invoice_number)) {
    return drupal_set_message(t('An invoice was not found or their was an error retrieving it.'), 'error');
  }

  // Initialize the Recurly client with the site-wide settings.
  if (!recurly_client_initialize()) {
    return t('Could not initialize the Recurly client.');
  }

  try {
    $pdf = Recurly_Invoice::getInvoicePdf($invoice_number, 'en-US');
    if (!empty($pdf)) {
      if (headers_sent()) {
        die("Unable to stream pdf: headers already sent");
      }

      drupal_add_http_header('Content-Type', 'application/pdf', TRUE);
      drupal_add_http_header('Content-Disposition', 'inline; filename="' . $invoice_number . '.pdf"', TRUE);
      // I guess below is not necessary plus filesize was not working anyway?!
      // drupal_add_http_header('Content-Transfer-Encoding', 'binary', TRUE);
      // drupal_add_http_header('Content-Length', filesize($pdf), TRUE);
      echo $pdf;
    }
  }
  catch (Recurly_NotFoundError $e) {
    drupal_set_message(t('Invoice not found'));
    return MENU_NOT_FOUND;
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
