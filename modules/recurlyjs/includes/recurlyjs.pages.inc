<?php
/**
 * @file
 * Menu callbacks for the Recurly.js module.
 */

/**
 * Form callback; Display the form for updating billing information.
 *
 * @param string $entity_type
 *   The type of the entity whose info is being updated.
 * @param object $entity
 *   The entity whose info is being updated.
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
      '#card_type' => \Drupal\Component\Utility\SafeMarkup::checkPlain($billing_info->card_type),
      '#first_name' => \Drupal\Component\Utility\SafeMarkup::checkPlain($billing_info->first_name),
      '#last_name' => \Drupal\Component\Utility\SafeMarkup::checkPlain($billing_info->last_name),
      '#year' => \Drupal\Component\Utility\SafeMarkup::checkPlain($billing_info->year),
      '#month' => \Drupal\Component\Utility\SafeMarkup::checkPlain($billing_info->month),
      '#mask_length' => strcasecmp($billing_info->card_type, 'American Express') === 0 ? 11 : 12,
      '#last_four' => \Drupal\Component\Utility\SafeMarkup::checkPlain($billing_info->last_four),
    );
  }
  catch (Recurly_NotFoundError $e) {
    \Drupal::logger('recurlyjs')->notice('Unable to retrieve billing information. Received the following error: @error', array('@error' => $e->getMessage()));
    drupal_set_message(t('Unable to retrieve billing information.'), 'error');
    return $form;
  }

  $form['#entity_type'] = $entity_type;
  $form['#entity'] = $entity;

  _recurlyjs_form_attach_js($form);
  _recurlyjs_append_billing_fields($form);
  // Populate #default_value with existing billing info.
  $excluded_fields = array('month', 'year');
  foreach (\Drupal\Core\Render\Element::children($form) as $form_element_name) {
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
