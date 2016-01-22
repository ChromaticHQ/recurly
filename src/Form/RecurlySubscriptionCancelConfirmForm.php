<?php

/**
 * @file
 * Contains \Drupal\recurly\Form\RecurlySubscriptionCancelConfirmForm.
 */

namespace Drupal\recurly\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;

/**
 * Recurly subscription cancel form controller.
 */
class RecurlySubscriptionCancelConfirmForm extends FormBase {

  const TERMINATE_NONE = 'terminate_none';
  const TERMINATE_PRORATED = 'terminate_prorated';
  const TERMINATE_FULL = 'terminate_full';

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'recurly_subscription_cancel_confirm_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $entity_type = NULL, $entity = NULL, $subscription = NULL) {
    // @TODO:
    // drupal_set_title() has been removed. There are now a few ways to set the
    // title dynamically, depending on the situation.
    //
    // @see https://www.drupal.org/node/2067859
    // drupal_set_title(t('Really cancel @plan?', [
    //   '@plan' => $subscription->plan->name,
    // ]), FALSE);
    //
    $form['#entity_type'] = $entity_type;
    $form['#entity'] = $entity;
    $form['#subscription'] = $subscription;
    $form['#subscription_in_trial'] = recurly_subscription_in_trial($subscription);

    parse_str($this->getRequest()->getQueryString(), $query_array);
    $past_due = isset($query_array['past_due']) && $query_array['past_due'] === '1';
    $admin_access = \Drupal::currentUser()->hasPermission('administer recurly');
    $in_trial = recurly_subscription_in_trial($subscription);

    // If in a trial, only cancel the account instead of terminating.
    if ($in_trial && !$admin_access) {
      $cancel_behavior = 'cancel';
    }
    else {
      $cancel_behavior = \Drupal::config('recurly.settings')->get('recurly_subscription_cancel_behavior');
    }

    $form['cancel'] = [
      '#access' => $admin_access || $cancel_behavior === 'cancel',
    ];
    $form['cancel']['description'] = [
      '#markup' => '<p>' . $this->t('Canceling a subscription will cause it not to renew. If you cancel the subscription, it will continue until <strong>@date</strong>. On that date, the subscription will expire and not be invoiced again. The subscription can be reactivated before it expires.',
        ['@date' => recurly_format_date($subscription->current_period_ends_at)]) . '</p>',
    ];
    $form['cancel']['actions'] = [
      '#type' => 'actions',
    ];
    $form['cancel']['actions']['cancel'] = [
      '#type' => 'submit',
      '#value' => $this->t('Cancel at Renewal'),
    ];

    $form['terminate'] = [
      '#access' => $admin_access || $cancel_behavior !== 'cancel',
    ];
    $form['terminate']['refund_amount'] = [
      '#type' => 'radios',
      '#title' => $this->t('Refund amount'),
      '#options' => [
        self::TERMINATE_NONE => $this->t('@amount - None', ['@amount' => recurly_format_currency(0, $subscription->currency)]),
      ],
      '#default_value' => $cancel_behavior === 'cancel' ? NULL : $cancel_behavior,
      '#weight' => 1,
      '#access' => $admin_access,
    ];

    if (!$past_due && $prorated_amount = recurly_subscription_calculate_refund($subscription, 'prorated')) {
      $form['terminate']['refund_amount']['#options'][self::TERMINATE_PRORATED] = $this->t('@amount - Prorated', ['@amount' => recurly_format_currency($prorated_amount, $subscription->currency)]);
    }
    if (!$past_due && $full_amount = recurly_subscription_calculate_refund($subscription, 'full')) {
      $form['terminate']['refund_amount']['#options'][self::TERMINATE_FULL] = $this->t('@amount - Full', ['@amount' => recurly_format_currency($full_amount, $subscription->currency)]);
    }

    $form['terminate']['admin_description'] = [
      '#type' => 'markup',
      '#markup' => '<p>' . $this->t('If you would like the subscription to end immediately, you may terminate the subscription now. Optionally, you may also issue a refund for the time remaining (prorated) or for the full amount. New subscriptions to this account will need to sign up for a new plan.') . '</p>',
      '#access' => $admin_access,
    ];

    // Use a more friendly description of the process for non-administrators.
    $friendly_description = $this->t('This subscription will be ended immediately. If you would like to subscribe again, you will need to start a new subscription.');
    if ($past_due) {
      $friendly_description .= '';
    }
    elseif ($cancel_behavior === self::TERMINATE_PRORATED) {
      $friendly_description .= ' ' . $this->t('A refund of @amount will be credited to your account.', ['@amount' => recurly_format_currency($prorated_amount, $subscription->currency)]);
    }
    elseif ($cancel_behavior === self::TERMINATE_FULL) {
      $friendly_description .= ' ' . $this->t('A refund of @amount will be credited to your account.', ['@amount' => recurly_format_currency($full_amount, $subscription->currency)]);
    }
    $form['terminate']['user_description'] = [
      '#type' => 'markup',
      '#markup' => '<p>' . $friendly_description . '</p>',
      '#access' => !$admin_access,
    ];
    $form['terminate']['actions'] = [
      '#type' => 'actions',
      '#weight' => 2,
    ];
    $form['terminate']['actions']['terminate'] = [
      '#type' => 'submit',
      '#value' => $admin_access ? $this->t('Terminate Immediately') : $this->t('Cancel Plan'),
    ];

    // Add a cancel option to the confirmation form.
    $form['actions']['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Cancel'),
      '#url' => \Drupal\Core\Url::fromRoute("entity.$entity_type.recurly_subscriptionlist", [$entity_type => $entity->id()]),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $entity = $form['#entity'];
    $entity_type = $form['#entity_type'];
    $subscription = $form['#subscription'];
    $clicked_button = $form_state->getValue('op');

    if ($form['cancel']['actions']['cancel']['#value'] === $clicked_button) {
      try {
        $subscription->cancel();
        drupal_set_message($this->t('Plan @plan canceled! It will expire on @date.', [
          '@plan' => $subscription->plan->name,
          '@date' => recurly_format_date($subscription->current_period_ends_at),
        ]));
        $form_state->setRedirect("entity.$entity_type.recurly_subscriptionlist", [$entity_type => $entity->id()]);
      }
      catch (\Recurly_Error $e) {
        drupal_set_message($this->t('The plan could not be canceled because the billing service encountered an error.'), 'error');
        return;
      }
    }
    elseif ($form['terminate']['actions']['terminate']['#value'] === $clicked_button) {
      try {
        switch ($form_state->getValue('refund_amount')) {
          case self::TERMINATE_NONE:
            $subscription->terminateWithoutRefund();
            break;

          case self::TERMINATE_PRORATED:
            $subscription->terminateAndPartialRefund();
            break;

          case self::TERMINATE_FULL:
            $subscription->terminateAndRefund();
            break;
        }
        drupal_set_message($this->t('Plan @plan terminated!', ['@plan' => $subscription->plan->name]));
        $form_state->setRedirect("entity.$entity_type.recurly_subscriptionlist", [$entity_type => $entity->id()]);
      }
      catch (\Recurly_Error $e) {
        drupal_set_message($this->t('The plan could not be terminated because the billing service encountered an error: "@message"', ['@message' => $e->getMessage()]), 'error');
        return;
      }
    }
  }

}
