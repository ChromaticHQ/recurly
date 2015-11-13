<?php
/**
 * @file
 * Menu callbacks for the Recurly.js module.
 */

/**
 * Form callback; Modifies the Recurly form at admin/config/services/recurly.
 */
function _recurlyjs_form_recurly_settings_form_alter(&$form, &$form_state) {
  $form['recurlyjs'] = array(
    '#title' => t('Recurly.js settings'),
    '#type' => 'fieldset',
    '#collapsible' => TRUE,
  );
  $form['recurlyjs']['recurlyjs_address_requirement'] = array(
    '#title' => t('Address requirement level'),
    '#type' => 'select',
    '#options' => array(
      'full' => t('Full'),
      'zipstreet' => t('Zip code and street'),
      'zip' => t('Zip code only'),
      'none' => t('None'),
    ),
    '#default_value' => variable_get('recurlyjs_address_requirement', 'full'),
    '#description' => t('Choose the level of address information required. Collecting more address information reduces the probability of fraudulent accounts. This setting should match the address requirements as configured on !link in "Site Settings".',
      array(
        '!link' => variable_get('recurly_subdomain', '') ? l(t('your Recurly account'),
        recurly_hosted_url('configuration/edit')) : t('your Recurly account'))),
  );
  $form['recurlyjs']['recurlyjs_enable_add_ons'] = array(
    '#title' => t('Enable Add-ons'),
    '#type' => 'checkbox',
    '#default_value' => variable_get('recurlyjs_enable_add_ons', 1),
    '#description' => t('Add-ons are options that may be displayed in addition to the base subscription. If a subscription does not have add-ons, nothing additional will be displayed.'),
  );
  $form['recurlyjs']['recurlyjs_enable_coupons'] = array(
    '#title' => t('Enable coupons'),
    '#type' => 'checkbox',
    '#default_value' => variable_get('recurlyjs_enable_coupons', 0),
    '#description' => t('Display the <em>Coupon Code</em> field on Recurly.js subscription forms.'),
  );
  $form['recurlyjs']['recurlyjs_accept_paypal'] = array(
    '#title' => t('Enable PayPal'),
    '#type' => 'checkbox',
    '#default_value' => variable_get('recurlyjs_accept_paypal', FALSE),
    '#description' => t('Show an option to pay with PayPal. This requires a PayPal Business account with Referential Transactions enabled. <a href="https://docs.recurly.com/payment-gateways/paypal-payments">See the Recurly Paypal documentation</a>.'),
  );

  $jquery_requirement = FALSE;
  if (module_exists('jquery_update')) {
    $jquery_version = variable_get('jquery_update_jquery_version', '1.10');
    if (version_compare('1.7', $jquery_version) < 0) {
      $jquery_requirement = TRUE;
    }
  }

  if (!$jquery_requirement) {
    $form['recurlyjs']['recurlyjs_accept_paypal']['#disabled'] = TRUE;
    $form['recurlyjs']['recurlyjs_accept_paypal']['#value'] = FALSE;
    $form['recurlyjs']['recurlyjs_accept_paypal']['#description'] .= ' <strong>' . t('To enable this feature please install <a href="http://drupal.org/project/jquery_update">jQuery Update</a> and use jQuery 1.7 or higher.') . '</strong>';
  }
}

/**
 * Menu callback; Display the page for starting a subscription.
 */
function recurlyjs_subscribe_page($entity_type, $entity, $plan_code, $currency = NULL) {
  // Initialize the Recurly client with the site-wide settings.
  if (!recurly_client_initialize()) {
    return t('Could not initialize the Recurly client.');
  }

  // Ensure the account does not already have this exact same plan. Recurly
  // does not support a single account having multiple of the same plan.
  list($id, $vid, $bundle) = entity_extract_ids($entity_type, $entity);
  $local_account = recurly_account_load(array('entity_type' => $entity_type, 'entity_id' => $id), TRUE);
  if ($local_account) {
    $current_subscriptions = recurly_account_get_subscriptions($local_account->account_code, 'active');

    // If the account is only allowed one subscription total, they shouldn't
    // ever see this signup page.
    if (variable_get('recurly_subscription_max', '1') === '1' && count($current_subscriptions) && empty($_POST)) {
      $current_subscription = reset($current_subscriptions);
      drupal_set_message(t('This account already has a @plan plan!', array('@plan' => $current_subscription->plan->name)));
      drupal_goto($entity_type . '/' . $id . '/subscription');
    }

    // Otherwise check if they already have one of this same plan.
    foreach ($current_subscriptions as $current_subscription) {
      if ($current_subscription->plan->plan_code === $plan_code && empty($_POST)) {
        drupal_set_message(t('This account already has a @plan plan!', array('@plan' => $current_subscription->plan->name)));
        drupal_goto($entity_type . '/' . $id . '/subscription/signup');
      }
    }
  }
  // Although this menu callback contains little else besides the subscription
  // form, it's a separate function because it's highly likely to need theming.
  $form = drupal_get_form('recurlyjs_subscribe_form', $entity_type, $entity, $plan_code, $currency);
  try {
    $plan = Recurly_Plan::get($plan_code);
  }
  catch (Recurly_NotFoundError $e) {
    return MENU_NOT_FOUND;
  }
  return theme('recurlyjs_subscribe_page', array('form' => $form));
}

/**
 * Form callback; Display the form for starting a subscription.
 */
function recurlyjs_subscribe_form($form, $form_state, $entity_type, $entity, $plan_code, $currency = NULL) {
  // Initialize the Recurly client with the site-wide settings.
  if (!recurly_client_initialize()) {
    $form['error'] = array(
      '#markup' => t('Could not initialize the Recurly client.'),
    );
    return $form;
  }

  $form['#entity_type'] = $entity_type;
  $form['#entity'] = $entity;
  $form['#plan_code'] = $plan_code;
  $form['#currency'] = $currency;

  $form['#prefix'] = '<div class="recurly-form-wrapper">';
  $form['#suffix'] = '</div>';

  _recurlyjs_append_billing_fields($form);
  _recurlyjs_form_attach_js($form);

  $form['actions'] = array(
    '#type' => 'actions',
  );
  $form['actions']['submit'] = array(
    '#type' => 'submit',
    '#value' => t('Purchase'),
  );
  return $form;
}

/**
 * Submit handler for recurlyjs_subscribe_form().
 */
function recurlyjs_subscribe_form_submit($form, &$form_state) {
  $entity_type = $form['#entity_type'];
  $entity = $form['#entity'];
  $plan_code = $form['#plan_code'];
  $currency = $form['#currency'] ?: variable_get('recurly_default_currency', 'USD');
  $recurly_token = isset($form_state['values']['recurly-token']) ? $form_state['values']['recurly-token'] : NULL;

  list($entity_id, $vid, $bundle) = entity_extract_ids($entity_type, $entity);
  $recurly_account = recurly_account_load(array('entity_type' => $entity_type, 'entity_id' => $entity_id));

  if (!$recurly_account) {
    $recurly_account = new Recurly_Account();
    // Account code is the only property required for Recurly account creation.
    // https://dev.recurly.com/docs/create-an-account
    $recurly_account->account_code = $entity_type . '-' . $entity_id;

    $recurly_account->billing_info = new Recurly_BillingInfo();
    $recurly_account->billing_info->token_id = $recurly_token;
    $recurly_account->create();
  }

  $subscription = new Recurly_Subscription();
  $subscription->account = $recurly_account;
  $subscription->plan_code = $plan_code;
  $subscription->currency = $currency;
  $subscription->create();

  drupal_set_message(t('Account upgraded to @plan!', array('@plan' => $subscription->plan->name)));

  // Save the account locally immediately so that subscriber information may
  // be retrieved when the user is directed back to the /subscription tab.
  try {
    $account = $subscription->account->get();
    recurly_account_save($account, $entity_type, $entity_id);
  }
  catch (Recurly_Error $e) {
    watchdog('recurly', 'New subscriber account could not be retreived from Recurly. Received the following error: @error', array('@error' => $e->getMessage()));
  }

  $form_state['redirect'] = $entity_type . '/' . $entity_id . '/subscription';
}

/**
 * Form callback; Display the form for updating billing information.
 */
function recurlyjs_update_billing_form($form, $form_state, $entity_type, $entity) {
  // Initialize the Recurly client with the site-wide settings.
  if (!recurly_client_initialize()) {
    $form['error'] = array(
      '#markup' => t('Could not initialize the Recurly client.'),
    );
    return $form;
  }

  // See if we have a local mapping of entity ID to Recurly account code.
  list($id, $vid, $bundle) = entity_extract_ids($entity_type, $entity);
  $recurly_account = recurly_account_load(array('entity_type' => $entity_type, 'entity_id' => $id));

  try {
    $billing_info = Recurly_BillingInfo::get($recurly_account->account_code);
    $form['existing'] = array(
      '#theme' => 'recurly_credit_card_information',
      '#card_type' => check_plain($billing_info->card_type),
      '#first_name' => check_plain($billing_info->first_name),
      '#last_name' => check_plain($billing_info->last_name),
      '#year' => check_plain($billing_info->year),
      '#month' => check_plain($billing_info->month),
      '#mask_length' => strcasecmp($billing_info->card_type, 'American Express') === 0 ? 11 : 12,
      '#last_four' => check_plain($billing_info->last_four),
    );
  }
  catch (Recurly_NotFoundError $e) {
    watchdog('recurlyjs', 'Unable to retrieve billing information:<br>!e', array('!e' => print_r($e, TRUE)), WATCHDOG_ERROR);
    drupal_set_message(t('Unable to retrieve billing information.'), 'error');
    return $form;
  }

  $form['#entity_type'] = $entity_type;
  $form['#entity'] = $entity;

  _recurlyjs_form_attach_js($form);
  _recurlyjs_append_billing_fields($form);
  // Populate #default_value with existing billing info.
  $excluded_fields = array('month', 'year');
  foreach (element_children($form) as $form_element_name) {
    if (!in_array($form_element_name, $excluded_fields)) {
      $form[$form_element_name]['#default_value'] = $form_element_name != 'postal_code' ? $billing_info->$form_element_name : $billing_info->zip;
    }
  }

  $form['actions'] = array(
    '#type' => 'actions',
  );
  $form['actions']['submit'] = array(
    '#type' => 'submit',
    '#value' => t('Update'),
    '#submit' => array('recurlyjs_update_billing_submit'),
  );
  return $form;
}

/**
 * Submit handler for recurlyjs_update_billing_form().
 */
function recurlyjs_update_billing_submit($form, $form_state) {
  $entity_type = $form['#entity_type'];
  $entity = $form['#entity'];
  $recurly_token = isset($form_state['values']['recurly-token']) ? $form_state['values']['recurly-token'] : NULL;

  list($id, $vid, $bundle) = entity_extract_ids($entity_type, $entity);
  $recurly_account = recurly_account_load(array('entity_type' => $entity_type, 'entity_id' => $id));

  if ($recurly_token && $recurly_account) {
    try {
      $billing_info = new Recurly_BillingInfo();
      $billing_info->account_code = $recurly_account->account_code;
      $billing_info->token_id = $recurly_token;
      $billing_info->update();
    }
    catch (Recurly_NotFoundError $e) {
      drupal_set_message(t('Could not find account or token is invalid or expired.'), 'error');
    }
  }
}

/**
 * Configure Form API elements for Recurly billing forms.
 *
 * @param array &$form
 *   A Drupal form array.
 */
function _recurlyjs_append_billing_fields(&$form) {
  $form['#prefix'] = '<div class="recurly-form-wrapper">';
  $form['#suffix'] = '</div>';

  // recurly-element.js adds errors here upon failed validation.
  $form['errors'] = array(
    '#markup' => '<div id="recurly-form-errors"></div>',
  );

  $form['first_name'] = array(
    '#type' => 'textfield',
    '#title' => t('First Name'),
    '#attributes' => array(
      'data-recurly' => 'first_name',
      '#name' => NULL,
    ),
    '#after_build' => array('_recurlyjs_form_remove_element_name'),
  );
  $form['last_name'] = array(
    '#type' => 'textfield',
    '#title' => t('Last Name'),
    '#attributes' => array(
      'data-recurly' => 'last_name',
    ),
    '#after_build' => array('_recurlyjs_form_remove_element_name'),
  );
  $form['address1'] = array(
    '#type' => 'textfield',
    '#title' => t('Address Line 1'),
    '#attributes' => array(
      'data-recurly' => 'address1',
    ),
    '#after_build' => array('_recurlyjs_form_remove_element_name'),
  );
  $form['address2'] = array(
    '#type' => 'textfield',
    '#title' => t('Address Line 2'),
    '#attributes' => array(
      'data-recurly' => 'address2',
    ),
    '#after_build' => array('_recurlyjs_form_remove_element_name'),
  );
  $form['city'] = array(
    '#type' => 'textfield',
    '#title' => t('City'),
    '#attributes' => array(
      'data-recurly' => 'city',
    ),
    '#after_build' => array('_recurlyjs_form_remove_element_name'),
  );
  $form['state'] = array(
    '#type' => 'textfield',
    '#title' => t('State'),
    '#attributes' => array(
      'data-recurly' => 'state',
    ),
    '#after_build' => array('_recurlyjs_form_remove_element_name'),
  );
  $form['postal_code'] = array(
    '#type' => 'textfield',
    '#title' => t('Postal Code'),
    '#attributes' => array(
      'data-recurly' => 'postal_code',
    ),
    '#after_build' => array('_recurlyjs_form_remove_element_name'),
  );
  $form['country'] = array(
    '#type' => 'textfield',
    '#title' => t('Country'),
    '#attributes' => array(
      'data-recurly' => 'country',
    ),
    '#after_build' => array('_recurlyjs_form_remove_element_name'),
  );
  $form['vat_number'] = array(
    '#type' => 'textfield',
    '#title' => t('VAT Number'),
    '#attributes' => array(
      'data-recurly' => 'vat_number',
    ),
    '#after_build' => array('_recurlyjs_form_remove_element_name'),
  );
  $form['number'] = array(
    '#type' => 'textfield',
    '#title' => t('Card Number'),
    '#attributes' => array(
      'data-recurly' => 'number',
    ),
    '#after_build' => array('_recurlyjs_form_remove_element_name'),
  );
  $form['month'] = array(
    '#type' => 'textfield',
    '#title' => t('MM'),
    '#attributes' => array(
      'data-recurly' => 'month',
    ),
    '#after_build' => array('_recurlyjs_form_remove_element_name'),
  );
  $form['year'] = array(
    '#type' => 'textfield',
    '#title' => t('YYYY'),
    '#attributes' => array(
      'data-recurly' => 'year',
    ),
    '#after_build' => array('_recurlyjs_form_remove_element_name'),
  );
  $form['tax_code'] = array(
    '#type' => 'hidden',
    '#title' => t('digital'),
    '#attributes' => array(
      'data-recurly' => 'tax_code',
    ),
    '#after_build' => array('_recurlyjs_form_remove_element_name'),
  );
  $form['recurly-token'] = array(
    '#type' => 'hidden',
    '#attributes' => array(
      'data-recurly' => 'token',
    ),
  );
}

/**
 * Element after_build callback to remove the input #name attribute.
 *
 * https://docs.recurly.com/js/#build-a-card-form
 */
function _recurlyjs_form_remove_element_name($form_element) {
  unset($form_element['#name']);
  return $form_element;
}

/**
 * Attach Recurly JS library to Drupal form.
 *
 * @param array &$form
 *   A Drupal form array.
 */
function _recurlyjs_form_attach_js(&$form) {
  $recurly_public_key = variable_get('recurly_public_key', '');

  // Add Recurly.js and the inline code to the page.
  $form['#attached']['js'][] = 'https://js.recurly.com/v3/recurly.js';
  $form['#attached']['js'][] = drupal_get_path('module', 'recurlyjs') . '/recurly-element.js';
  $form['#attached']['js'][] = array(
    'type' => 'inline',
    'data' => "/* Configure recurly.js. */  recurly.configure('$recurly_public_key');",
  );
}
