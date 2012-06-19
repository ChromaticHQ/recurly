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

  $entity_types = entity_get_info();
  $entity_options = array();
  foreach ($entity_types as $entity_name => $entity_info) {
    $entity_options[$entity_name] = $entity_info['label'];
    $first_bundle_name = key($entity_info['bundles']);
    // Don't generate a list of bundles if this entity does not have types.
    if ($entity_info['bundles'] > 1 && $first_bundle_name !== $entity_name) {
      foreach ($entity_info['bundles'] as $bundle_name => $bundle_info) {
        $entity_type_options[$entity_name][$bundle_name] = $bundle_info['label'];
      }
    }
  }

  // If any of the below options change we need to rebuild the menu system.
  // Keep a record of their current values.
  $recurly_entity_type = variable_get('recurly_entity_type', 'user');
  $form_state['pages_previous_values'] = array(
    'recurly_entity_type' => $recurly_entity_type,
    'recurly_bundle_' . $recurly_entity_type => variable_get('recurly_bundle_' . $recurly_entity_type),
    'recurly_pages' => variable_get('recurly_pages', '1'),
    'recurly_subscription_plans' => variable_get('recurly_subscription_plans', array()),
  );

  $form['sync'] = array(
    '#type' => 'fieldset',
    '#title' => t('Recurly account syncing'),
    '#collapsible' => TRUE,
    '#collapsed' => empty($recurly_entity_type),
    '#description' => t("Each time a particular object type (User, Node, Group, etc.) is updated, you may have information sent to Recurly to keep the contact information kept up-to-date. It is extremely important to maintain updated contact information in Recurly, as when an account enters the dunning process, the e-mail account in Recurly is the primary contact address."),
  );
  $form['sync']['recurly_entity_type'] = array(
    '#title' => t('Send Recurly account updates for each'),
    '#type' => 'select',
    '#options' => array('' => 'Nothing (disabled)') + $entity_options,
    '#default_value' => $recurly_entity_type,
  );
  foreach ($entity_type_options as $entity_name => $bundles) {
    $form['sync']['recurly_bundles']['recurly_bundle_' . $entity_name] = array(
      '#title' => t('Specifically the following @entity type', array('@entity' => $entity_types[$entity_name]['label'])),
      '#type' => 'select',
      '#options' => $bundles,
      '#default_value' => variable_get('recurly_bundle_' . $entity_name),
      '#states' => array(
        'visible' => array(
          'select[name="recurly_entity_type"]' => array('value' => $entity_name),
        ),
      ),
    );
  }
  $mapping = variable_get('recurly_token_mapping', array('email' => '[user:mail]', 'first_name' => '', 'last_name' => '', 'company' => ''));
  $form['sync']['recurly_token_mapping'] = array(
    '#title' => t('Token mappings'),
    '#type' => 'fieldset',
    '#collapsible' => TRUE,
    '#collapsed' => TRUE,
    '#tree' => TRUE,
    '#parents' => array('recurly_token_mapping'),
    '#description' => t('Each Recurly account field is displayed below, specify a token that will be used to update the Recurly account each time the object (node or user) is updated. The Recurly "username" field is automatically populated with the object name (for users) or title (for nodes).'),
  );
  $form['sync']['recurly_token_mapping']['email'] = array(
    '#title' => t('Email'),
    '#type' => 'textfield',
    '#default_value' => $mapping['email'],
    '#description' => t('i.e. [user:mail] or [node:author:mail]'),
  );
  $form['sync']['recurly_token_mapping']['first_name'] = array(
    '#title' => t('First name'),
    '#type' => 'textfield',
    '#default_value' => $mapping['first_name'],
  );
  $form['sync']['recurly_token_mapping']['last_name'] = array(
    '#title' => t('Last name'),
    '#type' => 'textfield',
    '#default_value' => $mapping['last_name'],
  );
  $form['sync']['recurly_token_mapping']['company'] = array(
    '#title' => t('Company'),
    '#type' => 'textfield',
    '#default_value' => $mapping['company'],
  );
  $form['sync']['recurly_token_mapping']['help'] = array(
    '#theme' => 'token_tree',
    '#token_types' => array_keys($entity_options),
    '#global_types' => FALSE,
  );
  $form['pages'] = array(
    '#type' => 'fieldset',
    '#title' => t('Built-in subscription/invoice pages'),
    '#collapsible' => TRUE,
    '#collapsed' => !variable_get('recurly_pages', '1'),
    '#description' => t('The Recurly module provides built-in pages for letting users view their own recent invoices on the site instead of needing to go to the Recurly site. If a companion module is enabled such as the Recurly Hosted Pages or Recurly.js module (both included with this project), appropriate links to update billing information or subscribe will also be displayed on these pages.'),
    '#states' => array(
      'visible' => array(
        'select[name="recurly_entity_type"]' => array('!value' => ''),
      ),
    ),
  );
  $form['pages']['recurly_pages'] = array(
    '#title' => t('Enable built-in pages'),
    '#type' => 'checkbox',
    '#default_value' => variable_get('recurly_pages', '1'),
    '#description' => t('Hosted pages will be enabled on the same object type as the "Account Syncing" option above.'),
  );

  // All the settings below are dependent upon the type being selected.
  $type_selected = array(
    'visible' => array(
      'input[name=recurly_pages]' => array('checked' => TRUE),
    ),
  );
  $form['pages']['recurly_subscription_display'] = array(
    '#title' => t('List subscriptions'),
    '#type' => 'radios',
    '#options' => array(
      'live' => t('Live subscriptions (active, trials, canceled, and past due)'),
      'all' => t('All (includes expired subscriptions)'),
    ),
    '#description' => t('Users may subscribe or switch between any of the enabled plans by visiting the Subsription tab.'),
    '#default_value' => variable_get('recurly_subscription_display', 'live'),
    '#states' => $type_selected,
  );
  try {
    $plans = recurly_subscription_plans();
  }
  catch (Recurly_Error $e) {
    $plans = array();
  }
  $plan_options = array();
  foreach ($plans as $plan) {
    $plan_options[$plan->plan_code] = $plan->name;
  }
  $form['pages']['recurly_subscription_plans'] = array(
    '#title' => t('Enabled plans'),
    '#type' => 'checkboxes',
    '#options' => $plan_options,
    '#description' => t('Users may subscribe or switch between any of the enabled plans by visiting the Subsription tab.'),
    '#access' => count($plan_options),
    '#default_value' => variable_get('recurly_subscription_plans', array_keys($plan_options)),
    '#states' => $type_selected,
  );
  $form['pages']['recurly_subscription_change_timeframe'] = array(
    '#title' => t('Change plan behavior'),
    '#type' => 'radios',
    '#options' => array(
      'now' => t('Change plan immediately (pro-rating billing period usage)'),
      'renewal' => t('On next renewal'),
    ),
    '#access' => count($plan_options),
    '#description' => t('Affects users who are able to change their own plan (if more than one is enabled). Overriddable when changing plans as users with "Administer Recurly" permission.'),
    '#enabled' => count($plan_options),
    '#default_value' => variable_get('recurly_subscription_change_timeframe', 'now'),
    '#states' => $type_selected,
  );
  $form['pages']['recurly_subscription_cancel_behavior'] = array(
    '#title' => t('Cancel plan behavior'),
    '#type' => 'radios',
    '#options' => array(
      'cancel' => t('Cancel at renewal (leave active until end of period)'),
      'terminate_prorated' => t('Terminate immediately (prorated refund)'),
      'terminate_full' => t('Terminate immediately (full refund)'),
    ),
    '#description' => t('Affects users canceling their own subscription plans. Overriddable when canceling plans as users with "Administer Recurly" permission. Note that this behavior is also used when content associated with a Recurly account is deleted, or when users associated with an account are canceled.'),
    '#enabled' => count($plan_options),
    '#default_value' => variable_get('recurly_subscription_cancel_behavior', 'cancel'),
    '#states' => $type_selected,
  );

  $form = system_settings_form($form);
  $form['#submit'][] = 'recurly_settings_form_submit';
  return $form;
}

/**
 * Trims user-supplied API text values.
 */
function recurly_settings_form_validate($form, &$form_state) {
  $keys = array(
    'recurly_api_key',
    'recurly_private_key',
    'recurly_subdomain',
    'recurly_listener_key',
  );
  foreach ($keys as $key) {
    $form_state['values'][$key] = trim($form_state['values'][$key]);
  }

  // Clean up checkbox values.
  $form_state['values']['recurly_subscription_plans'] = array_values(array_filter($form_state['values']['recurly_subscription_plans']));

  // Check that the API key is valid.
  if ($form_state['values']['recurly_api_key']) {
    try {
      $settings = array(
        'api_key' => $form_state['values']['recurly_api_key'],
        'private_key' => $form_state['values']['recurly_private_key'],
        'subdomain' => $form_state['values']['recurly_subdomain'],
      );
      recurly_client_initialize($settings, TRUE);
      $plans = recurly_subscription_plans();
    }
    catch (Recurly_UnauthorizedError $e) {
      form_set_error('recurly_api_key', t('Your API Key is not authorized to connect to Recurly.'));
    }
  }
}

/**
 * Submit handler for recurly_settings_form().
 */
function recurly_settings_form_submit($form, &$form_state) {
  // Rebuild the menu system if any of the built-in page options change.
  foreach ($form_state['pages_previous_values'] as $variable_name => $previous_value) {
    if (isset($form_state['values'][$variable_name]) && $form_state['values'][$variable_name] !== $previous_value) {
      menu_rebuild();
    }
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
    $operations[] = array(
      'title' => t('edit'),
      'href' => recurly_subscription_plan_edit_url($plan),
    );

    // Add a purchase link if Hosted Payment Pages are enabled.
    if (module_exists('recurly_hosted')) {
      $operations[] = array(
        'title' => t('purchase'),
        'href' => recurly_subscription_plan_purchase_url($plan),
      );
    }

    // TODO: Remove reset() calls once Recurly_CurrencyList implements Iterator.
    // See https://github.com/recurly/recurly-client-php/issues/37
    $unit_amounts = in_array('IteratorAggregate', class_implements($plan->unit_amount_in_cents)) ? $plan->unit_amount_in_cents : reset($plan->unit_amount_in_cents);
    $setup_fees = in_array('IteratorAggregate', class_implements($plan->setup_fee_in_cents)) ? $plan->setup_fee_in_cents : reset($plan->setup_fee_in_cents);

    $row = array();
    $row[] = check_plain($plan->name) . ' <small>(' . t('Plan code: @code', array('@code' => $plan->plan_code)) . ')</small>' . $description;

    $amount_strings = array();
    foreach ($unit_amounts as $unit_amount) {
      $amount_strings[] = t('@unit_price every @interval_length @interval_unit', array('@unit_price' => recurly_format_currency($unit_amount->amount_in_cents, $unit_amount->currencyCode), '@interval_length' => $plan->plan_interval_length, '@interval_unit' => $plan->plan_interval_unit));
    }
    $row[] = implode('<br />', $amount_strings);

    $setup_strings = array();
    foreach ($setup_fees as $setup_fee) {
      $setup_strings[] = recurly_format_currency($unit_amount->amount_in_cents, $unit_amount->currencyCode);
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
