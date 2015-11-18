<?php

/**
 * @file
 * Contains \Drupal\recurly\Form\RecurlySubscriptionPlansForm.
 */

namespace Drupal\recurly\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Component\Utility\SafeMarkup;

class RecurlySubscriptionPlansForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'recurly_subscription_plans_form';
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Order our variable based on the form order.
    $form_state_plans = $form_state->getValue('recurly_subscription_plans');
    $recurly_subscription_plans = array();
    foreach ($form_state->getUserInput()['weights'] as $plan_code => $weight) {
      if (isset($form_state_plans[$plan_code])) {
        $recurly_subscription_plans[$plan_code] = $form_state_plans[$plan_code];
      }
    }
    // Note that we don't actually need to care able the "weight" field values,
    // since the order of POST is actually changed based on the field position.
    \Drupal::configFactory()->getEditable('recurly.settings')->set('recurly_subscription_plans', $recurly_subscription_plans)->save();
    drupal_set_message(t('Status and order of subscription plans updated!'));

    parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['recurly.settings'];
  }

  public function buildForm(array $form, \Drupal\Core\Form\FormStateInterface $form_state) {
    // Initialize the Recurly client with the site-wide settings.
    if (!recurly_client_initialize()) {
      return t('Could not initialize the Recurly client.');
    }
    try {
      $plans = recurly_subscription_plans();
    }
    catch (Recurly_Error $e) {
      return t('No plans could be retrieved from Recurly. Recurly reported the following error: "@error"', array('@error' => $e->getMessage()));
    }
    $form['weights']['#tree'] = TRUE;

    $plan_options = array();
    $count = 0;
    foreach ($plans as $plan) {
      $plan_options[$plan->plan_code] = $plan->name;
      $form['#plans'][$plan->plan_code] = array(
        'plan' => $plan,
        'unit_amounts' => array(),
        'setup_amounts' => array(),
      );

      // TODO: Remove reset() calls once Recurly_CurrencyList implements Iterator.
      // See https://github.com/recurly/recurly-client-php/issues/37
      $unit_amounts = in_array('IteratorAggregate', class_implements($plan->unit_amount_in_cents)) ? $plan->unit_amount_in_cents : reset($plan->unit_amount_in_cents);
      $setup_fees = in_array('IteratorAggregate', class_implements($plan->setup_fee_in_cents)) ? $plan->setup_fee_in_cents : reset($plan->setup_fee_in_cents);
      foreach ($unit_amounts as $unit_amount) {
        $form['#plans'][$plan->plan_code]['unit_amounts'][$unit_amount->currencyCode] = t('@unit_price every @interval_length @interval_unit',
          array(
            '@unit_price' => recurly_format_currency($unit_amount->amount_in_cents, $unit_amount->currencyCode),
            '@interval_length' => $plan->plan_interval_length,
            '@interval_unit' => $plan->plan_interval_unit,
          ));
      }
      foreach ($setup_fees as $setup_fee) {
        $form['#plans'][$plan->plan_code]['setup_amounts'][$unit_amount->currencyCode] = recurly_format_currency($setup_fee->amount_in_cents, $setup_fee->currencyCode);
      }
      $form['weights'][$plan->plan_code] = array(
        '#type' => 'hidden',
        '#default_value' => $count,
        '#attributes' => array('class' => array('weight')),
      );
      $count++;
    }

    // Order our plans based on any existing value.
    $existing_plans = \Drupal::config('recurly.settings')->get('recurly_subscription_plans') ?: array();
    $plan_list = array();
    foreach ($existing_plans as $plan_code => $enabled) {
      if (isset($form['#plans'][$plan_code])) {
        $plan_list[$plan_code] = $form['#plans'][$plan_code];
      }
    }
    // Then add any new plans to the end.
    $plan_list += is_array($form['#plans']) ? $form['#plans'] : array();
    $form['#plans'] = $plan_list;

    foreach ($form['#plans'] as $plan_id => $details) {
      $operations = array();

      // Add an edit link if available for the current user.
      $operations['edit'] = array(
        'title' => t('edit'),
        'url' => recurly_subscription_plan_edit_url($details['plan']),
      );

      // Add a purchase link if Hosted Payment Pages are enabled.
      if (\Drupal::moduleHandler()->moduleExists('recurly_hosted')) {
        $operations['purchase'] = array(
          'title' => t('purchase'),
          'url' => recurly_hosted_subscription_plan_purchase_url($details['plan']->plan_code),
        );
      }

      $form['#plans'][$plan_id]['operations'] = array(
        '#theme' => 'links',
        '#links' => $operations,
        '#attributes' => array(
          'class' => array('links', 'inline'),
        ),
      );
    }

    $header = array(
      'plan_title' => array('data' => t('Subscription plan'), 'colspan' => 1),
      'price' => t('Price'),
      'setup_fee' => t('Setup fee'),
      'trial' => t('Trial'),
      'operations' => t('Operations'),
    );

    $options = array();
    foreach ($form['#plans'] as $plan_code => $plan_details) {
      $plan = $plan_details['plan'];

      $description = '';
      // Prepare the description string if one is given for the plan.
      if (!empty($plan->description)) {
        $description = '<div class="description">' . nl2br(SafeMarkup::checkPlain($plan->description)) . '</div>';
      }

      $form['recurly_subscription_plans'][$plan_code]['#title_display'] = 'none';
      $options[$plan_code] = array(
        'plan_title' => SafeMarkup::checkPlain($plan->name) . ' <small>(' . SafeMarkup::checkPlain($plan_code) . ')</small>' . $description,
        'price' => implode('<br />', $plan_details['unit_amounts']),
        'setup_fee' => implode('<br />', $plan_details['setup_amounts']),
        'trial' => $plan->trial_interval_length ? t('@trial_length @trial_unit', array('@trial_length' => $plan->trial_interval_length, '@trial_unit' => $plan->trial_interval_unit)) : t('No trial'),
        'operations' => drupal_render($plan_details['operations']),
      );
    }

    // @TODO: Implement draggable table.
    $form['recurly_subscription_plans'] = array(
      '#type' => 'tableselect',
      '#header' => $header,
      '#options' => $options,
      '#empty' => t('No subscription plans found. You can start by creating one in <a href=":url">your Recurly account</a>.', array(':url' => \Drupal::config('recurly.settings')->get('recurly_subdomain') ? recurly_hosted_url('plans') : 'http://app.recurly.com')),
      '#js_select' => FALSE,
      '#default_value' => $existing_plans,
      '#multiple' => TRUE,
    );

    $form['actions'] = array(
      '#type' => 'actions',
    );
    $form['actions']['submit'] = array(
      '#type' => 'submit',
      '#value' => t('Update plans'),
    );

    return $form;
  }
}
