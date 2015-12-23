<?php

/**
 * @file
 * Contains \Drupal\recurlyjs\Form\RecurlyJsUpdateBillingForm.
 */

namespace Drupal\recurlyjs\Form;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility\SafeMarkup;

/**
 * RecurlyJS update billing form.
 */
class RecurlyJsUpdateBillingForm extends RecurlyJsFormBase {

  const CARD_TYPE_AMEX = 'American Express';

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'recurlyjs_update_billing';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, EntityInterface $entity = NULL) {
    // Initialize the Recurly client with the site-wide settings.
    if (!recurly_client_initialize()) {
      $form['error'] = [
        '#markup' => t('Could not initialize the Recurly client.'),
      ];
      return $form;
    }

    $entity_type = $entity->getEntityType()->getLowercaseLabel();

    // See if we have a local mapping of entity ID to Recurly account code.
    $recurly_account = recurly_account_load(['entity_type' => $entity_type, 'entity_id' => $entity->id()]);

    try {
      $billing_info = \Recurly_BillingInfo::get($recurly_account->account_code);
      // Format expiration date.
      $exp_date = sprintf('%1$02d', SafeMarkup::checkPlain($billing_info->month)->__toString()) . '/' . SafeMarkup::checkPlain($billing_info->year);
      // Determine the correct number of masked card numbers depending on the
      // type of card.
      $mask_length = strcasecmp($billing_info->card_type, self::CARD_TYPE_AMEX) === 0 ? 11 : 12;
      $form['existing'] = [
        '#theme' => 'recurly_credit_card_information',
        '#card_type' => SafeMarkup::checkPlain($billing_info->card_type),
        '#first_name' => SafeMarkup::checkPlain($billing_info->first_name),
        '#last_name' => SafeMarkup::checkPlain($billing_info->last_name),
        '#exp_date' => $exp_date,
        '#last_four' => SafeMarkup::checkPlain($billing_info->last_four),
        '#card_num_masked' => str_repeat('x', $mask_length) . SafeMarkup::checkPlain($billing_info->last_four),
      ];
    }
    catch (Recurly_NotFoundError $e) {
      \Drupal::logger('recurlyjs')->notice('Unable to retrieve billing information. Received the following error: @error', ['@error' => $e->getMessage()]);
      drupal_set_message(t('Unable to retrieve billing information.'), 'error');
      return $form;
    }

    $form['#entity_type'] = $entity_type;
    $form['#entity'] = $entity;

    $form = parent::buildForm($form, $form_state);
    $excluded_fields = array('month', 'year');
    foreach (\Drupal\Core\Render\Element::children($form) as $form_element_name) {
      if (!in_array($form_element_name, $excluded_fields)) {
        $form[$form_element_name]['#default_value'] = ($form_element_name != 'postal_code') ? $billing_info->$form_element_name : $billing_info->zip;
      }
    }

    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => t('Update'),
      '#submit' => ['::submitForm'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    $entity_type = $form['#entity_type'];
    $entity = $form['#entity'];
    $recurly_token = $form_state->getValue('recurly-token');

    $entity_type = $entity->getEntityType()->getLowercaseLabel();
    $recurly_account = recurly_account_load(['entity_type' => $entity_type, 'entity_id' => $entity->id()]);

    if ($recurly_token && $recurly_account) {
      try {
        $billing_info = new \Recurly_BillingInfo();
        $billing_info->account_code = $recurly_account->account_code;
        $billing_info->token_id = $recurly_token;
        $billing_info->update();
      }
      catch (\Recurly_NotFoundError $e) {
        drupal_set_message(t('Could not find account or token is invalid or expired.'), 'error');
      }
    }
  }

}