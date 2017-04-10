<?php

namespace Drupal\recurlyjs\Form;

use Drupal\Core\Form\FormStateInterface;

/**
 * RecurlyJS subscribe form.
 */
class RecurlyJsSubscribeForm extends RecurlyJsFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'recurlyjs_subscribe';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $entity_type = NULL, $entity = NULL, $plan_code = NULL, $currency = NULL) {
    if (!$entity_type || !$entity || !$plan_code) {
      // @TODO: Replace exception.
      throw new Exception();
    }
    // Initialize the Recurly client with the site-wide settings.
    if (!recurly_client_initialize()) {
      $form['error'] = [
        '#markup' => $this->t('Could not initialize the Recurly client.'),
      ];
      return $form;
    }

    $form = parent::buildForm($form, $form_state);

    $form['#entity_type'] = $entity_type;
    $form['#entity'] = $entity;
    $form['#plan_code'] = $plan_code;
    $form['#currency'] = $currency ?: $this->config('recurly.settings')->get('recurly_default_currency') ?: 'USD';

    if ($this->config('recurlyjs.settings')->get('recurlyjs_enable_coupons')) {
      $form['coupon_code'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Coupon Code'),
        '#description' => $this->t('Recurly coupon code to be applied to subscription.'),
        '#element_validate' => ['::validateCouponCode'],
        '#weight' => -250,
      ];
    }
    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Purchase'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $entity_type = $form['#entity_type'];
    $entity = $form['#entity'];
    $plan_code = $form['#plan_code'];
    $currency = $form['#currency'];
    $recurly_token = $form_state->getValue('recurly-token');
    $coupon_code = $form_state->getValue('coupon_code');
    $recurly_account = recurly_account_load(['entity_type' => $entity_type, 'entity_id' => $entity->id()]);
    if (!$recurly_account) {
      $recurly_account = new \Recurly_Account();
      // Account code is the only property required for Recurly account
      // creation.
      // https://dev.recurly.com/docs/create-an-account
      $recurly_account->account_code = $entity_type . '-' . $entity->id();
      $recurly_account->billing_info = new \Recurly_BillingInfo();
      $recurly_account->billing_info->token_id = $recurly_token;
      if ($entity_type == 'user') {
        $recurly_account->email = $entity->getEmail();
        $recurly_account->username = $entity->getAccountName();
      }
    }
    try {
      $subscription = new \Recurly_Subscription();
      $subscription->account = $recurly_account;
      $subscription->plan_code = $plan_code;
      $subscription->currency = $currency;
      $subscription->coupon_code = $coupon_code;
      $subscription->create();
    }
    catch (\Recurly_ValidationError $e) {
      // There was an error validating information in the form. For example,
      // credit card was declined. We don't need to log these in Drupal, you can
      // find the errors logged within Recurly.
      drupal_set_message($this->t('<strong>Unable to create subscription:</strong><br/>@error', ['@error' => $e->getMessage()]), 'error');
      $form_state->setRebuild(TRUE);
      return;
    }
    catch (\Recurly_Error $e) {
      // Catch any non-validation errors. This will be things like unable to
      // contact Recurly API, or lower level errors. Display a generic message
      // to the user letting them know there was an error and then log the
      // detailed version. There's probably nothing a user can do to correct
      // these errors so we don't need to display the details.
      $this->logger('recurlyjs')->error('Unable to create subscription. Received the following error: @error', ['@error' => $e->getMessage()]);
      drupal_set_message($this->t('Unable to create subscription.'), 'error');
      $form_state->setRebuild(TRUE);
      return;
    }

    drupal_set_message($this->t('Account upgraded to @plan!', ['@plan' => $subscription->plan->name]));
    // Save the account locally immediately so that subscriber information may
    // be retrieved when the user is directed back to the /subscription tab.
    try {
      $account = $subscription->account->get();
      recurly_account_save($account, $entity_type, $entity->id());
    }
    catch (\Recurly_Error $e) {
      $this->logger('recurlyjs')->error('New subscriber account could not be retreived from Recurly. Received the following error: @error', ['@error' => $e->getMessage()]);
    }
    return $this->redirect("entity.$entity_type.recurly_subscriptionlist", [
      $entity->getEntityType()->getLowercaseLabel() => $entity->id(),
    ]);
  }

  /**
   * Element validate callback.
   */
  public function validateCouponCode($element, &$form_state, $form) {
    $coupon_code = isset($form_state['values']['coupon_code']) ? $form_state['values']['coupon_code'] : NULL;
    if (!$coupon_code) {
      return;
    }
    $currency = $form['#currency'];
    $plan_code = $form['#plan_code'];

    // Query Recurly to make sure this is a valid coupon code.
    try {
      $coupon = \Recurly_Coupon::get($coupon_code);
    }
    catch (\Recurly_NotFoundError $e) {
      form_error($element, $this->t('The coupon code you have entered is not valid.'));
      return;
    }
    // Check that the coupon is available in the specified currency.
    if ($coupon && $coupon->discount_type !== 'percent') {
      if (!$coupon->discount_in_cents->offsetExists($currency)) {
        form_error($element, $this->t('The coupon code you have entered is not valid in @currency.', ['@currency' => $currency]));
        return;
      }
    }
    // Check the the coupon is valid for the specified plan.
    if ($coupon && !$this->couponValidForPlan($coupon, $plan_code)) {
      form_error($element, $this->t('The coupon code you have entered is not valid for the specified plan.'));
      return;
    }
  }

  /**
   * Validate Recurly coupon against a specified plan.
   *
   * @todo Move to recurly.module?
   *
   * @param \Recurly_Coupon $recurly_coupon
   *   A Recurly coupon object.
   * @param string $plan_code
   *   A Recurly plan code.
   *
   * @return BOOL
   *   TRUE if the coupon is valid for the specified plan, else FALSE.
   */
  protected function couponValidForPlan(\Recurly_Coupon $recurly_coupon, $plan_code) {
    return ($recurly_coupon->applies_to_all_plans || in_array($plan_code, $recurly_coupon->plan_codes));
  }

}
