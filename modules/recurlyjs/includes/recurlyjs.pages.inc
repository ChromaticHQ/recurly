<?php
/**
 * @file
 * Menu callbacks for the RecurlyJS module.
 */

/**
 * Form callback; Modifies the Recurly form at admin/config/services/recurly.
 */
function _recurlyjs_form_recurly_settings_form_alter(&$form, &$form_state) {
  $form['recurlyjs'] = array(
    '#title' => t('RecurlyJS settings'),
    '#type' => 'fieldset',
    '#collapsible' => TRUE,
  );
  $form['recurlyjs']['recurlyjs_address_requirement'] = array(
    '#title' => t('Address requirement level'),
    '#type' => 'select',
    '#options' => array(
      'none' => t('None'),
      'zip' => t('Zip code'),
      'zipstreet' => t('Zip code and street'),
      'full' => t('Full'),
    ),
    '#default_value' => variable_get('recurlyjs_address_requirement', 'none'),
    '#description' => t('Choose the level of address information required. Collecting more address information reduces the probability of fraudulent accounts.'),
  );
}

function recurlyjs_update_billing($form, $form_state, $entity_type, $entity) {
  // Initialize the Recurly client with the site-wide settings.
  if (!recurly_client_initialize()) {
    $form['error'] = array(
      '#markup' => t('Could not initialize the Recurly client.'),
    );
    return $form;
  }

  // See if we have a local mapping of entity ID to Recurly account code.
  list($id, $vid, $bundle) = entity_extract_ids($entity_type, $entity);
  $account = recurly_account_load(array('entity_type' => $entity_type, 'entity_id' => $id));

  try {
    $billing_info = Recurly_BillingInfo::get($account->account_code);
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
  } catch (Recurly_NotFoundError $e) {
    $form['existing'] = array(
      '#markup' => t('None'),
    );
  }

  $form['update'] = array(
    '#type' => 'recurlyjs_billing',
    '#account_code' => $account->account_code,
    '#address_requirement' => variable_get('recurlyjs_address_requirement', 'none'),
  );

  $form['actions'] = array(
    '#type' => 'actions',
  );
  $form['actions']['submit'] = array(
    '#type' => 'submit',
    '#value' => t('Update'),
  );

  return $form;
}
