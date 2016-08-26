<?php

namespace Drupal\recurly\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\recurly\RecurlyFormatManager;
use Drupal\recurly\RecurlyUrlManager;

/**
 * Recurly subscription plans form.
 */
class RecurlySubscriptionPlansForm extends ConfigFormBase {

  /**
   * The formatting service.
   *
   * @var \Drupal\recurly\RecurlyFormatManager
   */
  protected $recurlyFormatter;

  /**
   * The Recurly Url service.
   *
   * @var \Drupal\recurly\RecurlyUrlManager
   */
  protected $recurlyUrlManager;

  /**
   * Constructs a \Drupal\recurly\Form\RecurlySubscriptionPlansForm object.
   *
   * @param \Drupal\recurly\RecurlyFormatManager $recurly_formatter
   *   The Recurly formatter to be used for formatting.
   * @param \Drupal\recurly\RecurlyUrlManager $recurly_url_manager
   *   The Recurly URL service to be used for generating URLs.
   */
  public function __construct(RecurlyFormatManager $recurly_formatter, RecurlyUrlManager $recurly_url_manager) {
    $this->recurlyFormatter = $recurly_formatter;
    $this->recurlyUrlManager = $recurly_url_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('recurly.format_manager'),
      $container->get('recurly.url_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'recurly_subscription_plans_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['recurly.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Table header definition.
    $header = [
      'status' => $this->t('Status'),
      'plan_title' => $this->t('Subscription plan'),
      'price' => $this->t('Price'),
      'setup_fee' => $this->t('Setup fee'),
      'trial' => $this->t('Trial'),
      'weight' => $this->t('Weight'),
      'operations' => $this->t('Operations'),
    ];

    // Pre-populate the table with existing plans and their weights/statuses.
    $existing_plans = $this->config('recurly.settings')->get('recurly_subscription_plans') ?: [];
    $form['recurly_subscription_plans'] = [
      '#type' => 'table',
      '#header' => $header,
      '#empty' => $this->t('No subscription plans found. You can start by creating one in <a href=":url">your Recurly account</a>.', [
        ':url' => $this->config('recurly.settings')->get('recurly_subdomain') ?
        $this->recurlyUrlManager->hostedUrl('plans')->getUri() : 'https://app.recurly.com',
      ]),
      '#default_value' => $existing_plans,
      '#tabledrag' => [
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'plan-weight',
        ],
      ],
    ];

    // Initialize the Recurly client with the site-wide settings.
    if (!recurly_client_initialize()) {
      return ['#markup' => $this->t('Could not initialize the Recurly client.')];
    }
    try {
      $plans = recurly_subscription_plans();
    }
    catch (\Recurly_Error $e) {
      return $this->t('No plans could be retrieved from Recurly. Recurly reported the following error: "@error"', ['@error' => $e->getMessage()]);
    }

    // Add plans from Recurly API and update existing ones.
    $plans_array = [];
    foreach ($plans as $plan) {
      // Use an array to update/create plans, and to determine existing plans.
      $plans_array[$plan->plan_code] = [
        'plan' => $plan,
        'unit_amounts' => [],
        'setup_amounts' => [],
      ];

      // If the plan exists then update it. Otherwise add new plan.
      if (array_key_exists($plan->plan_code, $existing_plans)) {
        // Prevent error caused from schema change.
        if (!is_array($existing_plans[$plan->plan_code])) {
          $existing_plans[$plan->plan_code] = empty($existing_plans[$plan->plan_code]) ? ['status' => 0] : ['status' => 1];
        }
        // Update plan.
        $existing_plans[$plan->plan_code] += $plans_array[$plan->plan_code];
      }
      else {
        // Add new plan.
        $existing_plans[$plan->plan_code] = $plans_array[$plan->plan_code];
      }

      // TODO: Remove reset() calls once Recurly_CurrencyList implements
      // Iterator.
      // See https://github.com/recurly/recurly-client-php/issues/37
      $unit_amounts = in_array('IteratorAggregate', class_implements($plan->unit_amount_in_cents)) ? $plan->unit_amount_in_cents : reset($plan->unit_amount_in_cents);
      $setup_fees = in_array('IteratorAggregate', class_implements($plan->setup_fee_in_cents)) ? $plan->setup_fee_in_cents : reset($plan->setup_fee_in_cents);
      foreach ($unit_amounts as $unit_amount) {
        $existing_plans[$plan->plan_code]['unit_amounts'][$unit_amount->currencyCode] = $this->t('@unit_price every @interval_length @interval_unit',
          [
            '@unit_price' => $this->recurlyFormatter->formatCurrency($unit_amount->amount_in_cents, $unit_amount->currencyCode),
            '@interval_length' => $plan->plan_interval_length,
            '@interval_unit' => $plan->plan_interval_unit,
          ]
        );
        foreach ($setup_fees as $setup_fee) {
          $existing_plans[$plan->plan_code]['setup_amounts'][$unit_amount->currencyCode] = $this->recurlyFormatter->formatCurrency($setup_fee->amount_in_cents, $setup_fee->currencyCode);
        }
      }
    }

    // Remove non-existent/deleted plans.
    $existing_plans = array_intersect_key($existing_plans, $plans_array);

    // Theme plans.
    foreach ($existing_plans as $plan_code => $plan_details) {
      $plan = $plan_details['plan'];
      $plan_weight = isset($existing_plans[$plan_code]['weight']) ? $existing_plans[$plan_code]['weight'] : 0;
      $plan_status = isset($existing_plans[$plan_code]['status']) ? $existing_plans[$plan_code]['status'] : 0;
      $row =& $form['recurly_subscription_plans'][$plan_code];

      // TableDrag: Mark the table row as draggable.
      $row['#attributes']['class'][] = 'draggable';
      // Sort the table row according to its existing/configured weight.
      $row['#weight'] = $plan_weight;

      // Poor man's TableSelect: Lacks a 'Select All' but works better with
      // TableDrag.
      $row['status'] = [
        '#type' => 'checkbox',
        '#default_value' => $plan_status,
      ];

      // Prepare the description string if one is given for the plan.
      $description = '';
      if (!empty($plan->description)) {
        $description = $this->t('<div class="description">@description</div>', ['@description' => $plan->description]);
      }

      // Compose plan title from the plan's name, code, and description.
      $row['plan_title'] = [
        '#markup' => $this->t(
          '@planname <small>(@plancode)</small> @description',
          [
            '@planname' => $plan->name,
            '@plancode' => $plan_code,
            '@description' => $description,
          ]
        ),
      ];

      $row['price'] = [
        '#markup' => implode('<br />', $plan_details['unit_amounts']),
      ];

      $row['setup_fee'] = [
        '#markup' => implode('<br />', $plan_details['setup_amounts']),
      ];

      $row['trial'] = [
        '#markup' => $plan->trial_interval_length ? $this->t(
          '@trial_length @trial_unit',
          [
            '@trial_length' => $plan->trial_interval_length,
            '@trial_unit' => $plan->trial_interval_unit,
          ]
        ) : $this->t('No trial'),
      ];

      // TableDrag: Weight column element.
      $row['weight'] = [
        '#type' => 'weight',
        '#title' => t('Weight for @title', array('@title' => $plan->name)),
        '#title_display' => 'invisible',
        '#size' => 1,
        '#default_value' => $plan_weight,
        // Classify the weight element for #tabledrag.
        '#attributes' => array('class' => array('plan-weight')),
      ];

      // Define and instantiate operations for each row.
      $operations = [];

      // Add an edit link if available for the current user.
      $operations['edit'] = [
        'title' => $this->t('edit'),
        'url' => $this->recurlyUrlManager->planEditUrl($plan),
      ];

      // Add a purchase link if Hosted Payment Pages are enabled.
      if (\Drupal::moduleHandler()->moduleExists('recurly_hosted')) {
        $operations['purchase'] = [
          'title' => $this->t('purchase'),
          'url' => recurly_hosted_subscription_plan_purchase_url($plan->plan_code),
        ];
      }

      $row['operations'] = [
        '#type' => 'operations',
        '#links' => $operations,
      ];
    }

    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Update plans'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Save plan weights and statuses to config.
    $recurly_subscription_plans = $form_state->getValue('recurly_subscription_plans');
    $this->config('recurly.settings')->set('recurly_subscription_plans', $recurly_subscription_plans)->save();
    drupal_set_message($this->t('Status and order of subscription plans updated!'));
  }

}
