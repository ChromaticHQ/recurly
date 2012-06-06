<?php

/**
 * @file
 * Recurly settings forms and administration page callbacks.
 */

/**
 * Returns the site-wide Recurly settings form.
 */
function recurly_settings_form($form, &$form_state) {
  // Add form elements to collect default account information.
  $form['account'] = array(
    '#type' => 'fieldset',
    '#title' => t('Default account settings'),
    '#description' => t('Configure this information based on the "API Credentials" section within the Recurly administration interface.'),
    '#collapsible' => TRUE,
  );
  $form['account']['recurly_api_key'] = array(
    '#type' => 'textfield',
    '#title' => t('API Key'),
    '#default_value' => variable_get('recurly_api_key', ''),
  );
  $form['account']['recurly_private_key'] = array(
    '#type' => 'textfield',
    '#title' => t('Recurly Private Key'),
    '#description' => t('Optional: Recurly Private Key - enter this if needed for transparent post/recurly.js verifications.'),
    '#default_value' => variable_get('recurly_private_key', ''),
  );
  $form['account']['recurly_subdomain'] = array(
    '#type' => 'textfield',
    '#title' => t('Subdomain'),
    '#description' => t("The subdomain of your account including the -test suffix if using the Sandbox."),
    '#default_value' => variable_get('recurly_subdomain', ''),
  );
  $form['account']['recurly_hosted_payment_pages'] = array(
    '#type' => 'checkbox',
    '#title' => t('Hosted Payment Pages are enabled for this account.'),
    '#default_value' => variable_get('recurly_hosted_payment_pages', FALSE),
  );

  // Add form elements to configure default push notification settings.
  $form['push'] = array(
    '#type' => 'fieldset',
    '#title' => t('Push notification settings'),
    '#description' => t('If you have supplied an HTTP authentication username and password in your Push Notifications settings at Recurly, your web server must be configured to validate these credentials at your listener URL.'),
    '#collapsible' => TRUE,
  );
  $form['push']['recurly_listener_key'] = array(
    '#type' => 'textfield',
    '#title' => t('Listener URL key'),
    '#description' => t('Customizing the listener URL gives you protection against fraudulent push notifications.') . '<br />' . t('Based on your current key, you should set @url as your Push Notification URL at Recurly.', array('@url' => url('recurly/listener/' . variable_get('recurly_listener_key', ''), array('absolute' => TRUE)))),
    '#default_value' => variable_get('recurly_listener_key', ''),
    '#required' => TRUE,
    '#size' => 32,
    '#field_prefix' => url('recurly/listener/', array('absolute' => TRUE)),
  );
  $form['push']['recurly_push_logging'] = array(
    '#type' => 'checkbox',
    '#title' => t('Log authenticated incoming push notifications. (Primarily used for debugging purposes.)'),
    '#default_value' => variable_get('recurly_push_logging', FALSE),
  );

  // Add form elements allowing the administrator to toggle integration options.
  $form['integration'] = array(
    '#type' => 'fieldset',
    '#title' => t('Integration options'),
    '#collapsible' => TRUE,
  );
  $form['integration']['recurly_account_integration'] = array(
    '#type' => 'checkboxes',
    '#title' => t('Account integration'),
    '#options' => array(
      'profile_display' => t('Display Recurly account information on user profile pages, including account management links if Hosted Payment Pages are enabled.'),
      'push_create' => t('Create local account records upon receipt of push notifications linked to users by e-mail address.'),
      'push_update' => t('Update local account records upon receipt of push notifications.'),
    ),
    '#default_value' => variable_get('recurly_account_integration', array()),
  );

  return system_settings_form($form);
}

/**
 * Trims user-supplied API text values.
 */
function recurly_settings_form_validate($form, &$form_state) {
  static $keys = array(
    'recurly_api_key',
    'recurly_private_key',
    'recurly_subdomain',
    'recurly_listener_key',
  );
  foreach ($keys as $key) {
    $form_state['values'][$key] = trim($form_state['values'][$key]);
  }
}

/**
 * Displays a list of subscription plans currently defined in your Recurly account.
 */
function recurly_subscription_plans_overview() {
  // Initialize the Recurly client with the site-wide settings.
  if (!recurly_client_initialize()) {
    return t('Could not initialize the Recurly client.');
  }

  // Retrieve the plans for the current account.
  $plans = recurly_subscription_plans();

  // Format the plan data into a table for display.
  $header = array(t('Subscription plan'), t('Price'), t('Setup fee'), t('Trial'), t('Created'), t('Operations'));
  $rows = array();

  foreach ($plans as $plan) {
    $operations = array();
    $description = '';

    // Prepare the description string if one is given for the plan.
    if (!empty($plan->description)) {
      $description = '<div class="description">' . check_plain($plan->description) . '</div>';
    }

    // Add an edit link if available for the current user.
    if (user_access('edit subscription plans')) {
      $operations[] = array(
        'title' => t('edit'),
        'href' => recurly_subscription_plan_edit_url($plan),
      );
    }

    // Add a purchase link if Hosted Payment Pages are enabled.
    if (variable_get('recurly_hosted_payment_pages', FALSE)) {
      $operations[] = array(
        'title' => t('purchase'),
        'href' => recurly_subscription_plan_purchase_url($plan),
      );
    }

    // TODO: Remove reset() calls once Recurly_CurrencyList implements Iterator.
    // See https://github.com/recurly/recurly-client-php/issues/37
    $unit_amounts = in_array('Iterator', class_implements($plan->unit_amount_in_cents)) ? $plan->unit_amount_in_cents : reset($plan->unit_amount_in_cents);
    $setup_fees = in_array('Iterator', class_implements($plan->setup_fee_in_cents)) ? $plan->setup_fee_in_cents : reset($plan->setup_fee_in_cents);

    $row = array();
    $row[] = check_plain($plan->name) . ' <small>(' . t('Plan code: @code', array('@code' => $plan->plan_code)) . ')</small>' . $description;

    $amount_strings = array();
    foreach ($unit_amounts as $unit_amount) {
      $amount_strings[] = t('@unit_price per @interval_length @interval_unit', array('@unit_price' => number_format($unit_amount->amount(), 2) . ' ' . $unit_amount->currencyCode, '@interval_length' => $plan->plan_interval_length, '@interval_unit' => $plan->plan_interval_unit));
    }
    $row[] = implode('<br />', $amount_strings);

    $setup_strings = array();
    foreach ($setup_fees as $setup_fee) {
      $setup_strings[] = check_plain(number_format($unit_amount->amount(), 2) . ' ' . $unit_amount->currencyCode);
    }
    $row[] = implode('<br />', $setup_strings);

    $row[] = t('@trial_length @trial_unit', array('@trial_length' => $plan->trial_interval_length, '@trial_unit' => $plan->trial_interval_unit));
    $row[] = format_date($plan->created_at->format('U'));
    $row[] = theme('links', array('links' => $operations, 'attributes' => array('class' => array('links', 'inline'))));
    $rows[] = $row;
  }

  if (empty($rows)) {
    $rows[] = array(array('data' => t('No subscription plans found.'), 'colspan' => 5));
  }

  return theme('table', array('header' => $header, 'rows' => $rows));
}
