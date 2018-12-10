<?php

namespace Drupal\recurlyjs\Form;

use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Routing\RouteMatchInterface;

/**
 * RecurlyJS update billing form.
 */
class RecurlyJsUpdateBillingForm extends RecurlyJsFormBase {

  /**
   * Card type string to match against Recurly response.
   */
  const CARD_TYPE_AMEX = 'American Express';

  /**
   * American Express card number length.
   */
  const CARD_LENGTH_AMEX = 11;

  /**
   * Standard credit card length.
   */
  const CARD_LENGTH_OTHER = 12;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'recurlyjs_update_billing';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, RouteMatchInterface $route_match = NULL) {
    $entity_type_id = $this->config('recurly.settings')->get('recurly_entity_type');
    $entity = $route_match->getParameter($entity_type_id);

    $entity_type = $entity->getEntityType()->getLowercaseLabel();

    // See if we have a local mapping of entity ID to Recurly account code.
    $recurly_account = recurly_account_load(['entity_type' => $entity_type, 'entity_id' => $entity->id()]);

    try {
      $billing_info = \Recurly_BillingInfo::get($recurly_account->account_code);
      // Format expiration date.
      $exp_date = sprintf('%1$02d', Html::escape($billing_info->month)) . '/' . Html::escape($billing_info->year);
      // Determine the correct number of masked card numbers depending on the
      // type of card.
      $mask_length = strcasecmp($billing_info->card_type, self::CARD_TYPE_AMEX) === 0 ? self::CARD_LENGTH_AMEX : self::CARD_LENGTH_OTHER;
      $form['existing'] = [
        '#theme' => 'recurly_credit_card_information',
        '#card_type' => Html::escape($billing_info->card_type),
        '#first_name' => Html::escape($billing_info->first_name),
        '#last_name' => Html::escape($billing_info->last_name),
        '#exp_date' => $exp_date,
        '#last_four' => Html::escape($billing_info->last_four),
        '#card_num_masked' => str_repeat('x', $mask_length) . Html::escape($billing_info->last_four),
      ];
    }
    catch (\Recurly_NotFoundError $e) {
      $this->logger('recurlyjs')->notice('Unable to retrieve billing information. Received the following error: @error', ['@error' => $e->getMessage()]);
      drupal_set_message($this->t('Unable to retrieve billing information.'), 'error');
      return $form;
    }

    $form['#entity_type'] = $entity_type;
    $form['#entity'] = $entity;

    $form = parent::buildForm($form, $form_state);
    $excluded_fields = ['month', 'year'];
    foreach (Element::children($form) as $form_element_name) {
      if (!in_array($form_element_name, $excluded_fields)) {
        $form[$form_element_name]['#default_value'] = ($form_element_name != 'postal_code') ? $billing_info->$form_element_name : $billing_info->zip;
      }
    }

    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Update'),
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
      catch (\Recurly_ValidationError $e) {
        // There was an error validating information in the form. For example,
        // credit card was declined. Let the user know. These errors are logged
        // in Recurly.
        drupal_set_message($this->t('<strong>Unable to update account:</strong><br/>@error', ['@error' => $e->getMessage()]), 'error');
      }
      catch (\Recurly_NotFoundError $e) {
        drupal_set_message($this->t('Could not find account or token is invalid or expired.'), 'error');
        $form_state->setRebuild(TRUE);
      }
      catch (\Recurly_Error $e) {
        // Catch all other errors. Log the details, and display a message for
        // the user.
        $this->logger('recurlyjs')->error('Billing information update error: @error', ['@error' => $e->getMessage()]);
        drupal_set_message($this->t('An error occured while trying to update your account. Please contact a site administrator.'));
        $form_state->setRebuild(TRUE);
      }
    }
  }

}
