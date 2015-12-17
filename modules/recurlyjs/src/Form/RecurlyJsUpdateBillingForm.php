<?php

/**
 * @file
 * Contains \Drupal\recurlyjs\Form\RecurlyJsUpdateBillingForm.
 */

namespace Drupal\recurlyjs\Form;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility\SafeMarkup;

/**
 * RecurlyJS update billing form.
 */
class RecurlyJsUpdateBillingForm extends FormBase {

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
      $form['existing'] = [
        '#theme' => 'recurly_credit_card_information',
        '#card_type' => SafeMarkup::checkPlain($billing_info->card_type),
        '#first_name' => SafeMarkup::checkPlain($billing_info->first_name),
        '#last_name' => SafeMarkup::checkPlain($billing_info->last_name),
        '#year' => SafeMarkup::checkPlain($billing_info->year),
        '#month' => SafeMarkup::checkPlain($billing_info->month),
        '#mask_length' => strcasecmp($billing_info->card_type, 'American Express') === 0 ? 11 : 12,
        '#last_four' => SafeMarkup::checkPlain($billing_info->last_four),
      ];
    }
    catch (Recurly_NotFoundError $e) {
      \Drupal::logger('recurlyjs')->notice('Unable to retrieve billing information. Received the following error: @error', ['@error' => $e->getMessage()]);
      drupal_set_message(t('Unable to retrieve billing information.'), 'error');
      return $form;
    }

    $form['#entity_type'] = $entity_type;
    $form['#entity'] = $entity;

    _recurlyjs_form_attach_js($form);
    $this->appendBillingFields($form);
    // @FIXME: Populate #default_value with existing billing info.

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
  }

}
