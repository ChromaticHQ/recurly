<?php

namespace Drupal\recurly\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Locale\CountryManagerInterface;
use Drupal\Core\Routing\RouteBuilderInterface;
use Drupal\recurly\RecurlyUrlManager;
use Drupal\recurly\RecurlyTokenManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Recurly configuration settings form.
 */
class RecurlySettingsForm extends ConfigFormBase {

  /**
   * The Recurly URL manager service.
   *
   * @var \Drupal\recurly\RecurlyUrlManager
   */
  protected $recurlyUrlManager;

  /**
   * The Recurly token manager service.
   *
   * @var \Drupal\recurly\RecurlyTokenManager
   */
  protected $recurlyTokenManager;

  /**
   * The entity type bundle info service.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfo;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The country manager service.
   *
   * @var \Drupal\Core\Locale\CountryManagerInterface
   */
  protected $countryManager;

  /**
   * The router builder service.
   *
   * @var \Drupal\Core\Routing\RouteBuilderInterface
   */
  protected $routeBuilder;

  /**
   * Creates a Recurly settings form.
   *
   * @param \Drupal\recurly\RecurlyUrlManager $recurly_url_manager
   *   The Recurly URL manager service.
   * @param \Drupal\recurly\RecurlyTokenManager $recurly_token_manager
   *   The Recurly token manager service.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_bundle_info
   *   The entity type bundle info service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Locale\CountryManagerInterface $country_manager
   *   The country manager service.
   * @param \Drupal\Core\Routing\RouteBuilderInterface $route_builder
   *   The router builder service.
   */
  public function __construct(
    RecurlyUrlManager $recurly_url_manager,
    RecurlyTokenManager $recurly_token_manager,
    EntityTypeBundleInfoInterface $entity_bundle_info,
    EntityTypeManagerInterface $entity_type_manager,
    CountryManagerInterface $country_manager,
    RouteBuilderInterface $route_builder
    ) {
    $this->recurlyUrlManager = $recurly_url_manager;
    $this->recurlyTokenManager = $recurly_token_manager;
    $this->entityTypeBundleInfo = $entity_bundle_info;
    $this->entityTypeManager = $entity_type_manager;
    $this->countryManager = $country_manager;
    $this->routeBuilder = $route_builder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('recurly.url_manager'),
      $container->get('recurly.token_manager'),
      $container->get('entity_type.bundle.info'),
      $container->get('entity_type.manager'),
      $container->get('country_manager'),
      $container->get('router.builder')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'recurly_settings_form';
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
    // Recommend setting some subscription plans if not enabled.
    $plan_options = $this->config('recurly.settings')->get('recurly_subscription_plans') ?: [];

    if (empty($plan_options) && $this->config('recurly.settings')->get('recurly_private_api_key') && $this->config('recurly.settings')->get('recurly_pages')) {
      drupal_set_message($this->t('Recurly built-in pages are enabled, but no plans have yet been enabled. Enable plans on the <a href=":url">Subscription Plans page</a>.', [
        ':url' => Url::fromRoute('recurly.subscription_plans_overview')->toString(),
      ]), 'warning', FALSE);
    }

    // Add form elements to collect default account information.
    $form['account'] = [
      '#type' => 'details',
      '#title' => $this->t('Default account settings'),
      '#description' => $this->t('Configure this information based on the "API Credentials" section within the Recurly administration interface.'),
      '#open' => TRUE,
    ];
    $form['account']['recurly_private_api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Private API Key'),
      '#default_value' => $this->config('recurly.settings')->get('recurly_private_api_key'),
    ];
    $form['account']['recurly_public_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Public Key'),
      '#description' => $this->t('Enter this if needed for Recurly.js. Note that this version of the Recurly module only supports Recurly.js v4, which uses the "public key" and not the "transparent post key" used by Recurly.js v2.'),
      '#default_value' => $this->config('recurly.settings')->get('recurly_public_key'),
    ];
    $recurly_subdomain = $this->config('recurly.settings')->get('recurly_subdomain');
    $form['account']['recurly_subdomain'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Subdomain'),
      '#description' => $this->t("The subdomain of your account."),
      '#default_value' => $recurly_subdomain,
    ];
    // If subdomain isn't empty, then set currency suggestion and link,
    // otherwise leave blank.
    $currency_suggestion = !empty($recurly_subdomain) ? t('@spaceYou can find a list of supported currencies in your <a href=":url">Recurly account currencies page</a>.', [
      '@space' => ' ',
      ':url' => $this->recurlyUrlManager->hostedUrl('configuration/currencies')->getUri(),
    ]) : '';
    $currencies = array_keys(recurly_currency_list());
    $form['account']['recurly_default_currency'] = [
      '#type' => 'select',
      '#title' => $this->t('Default currency'),
      '#description' => $this->t('Select the 3-character currency code for the currency you would like to use by default.:currency_suggestion', [
        ':currency_suggestion' => $currency_suggestion,
      ]),
      '#default_value' => $this->config('recurly.settings')->get('recurly_default_currency'),
      '#options' => array_combine($currencies, $currencies),
    ];

    // Add form elements to configure default push notification settings.
    $form['push'] = [
      '#type' => 'details',
      '#title' => $this->t('Push notification settings'),
      '#description' => $this->t('If you have supplied an HTTP authentication username and password in your Push Notifications settings at Recurly, your web server must be configured to validate these credentials at your listener URL.'),
      '#open' => TRUE,
    ];
    $form['push']['recurly_listener_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Listener URL key'),
      '#description' => $this->t('Customizing the listener URL gives you protection against fraudulent push notifications.') . '<br />' . $this->t('Based on your current key, you should set @url as your Push Notification URL at Recurly.',
        [
          '@url' => Url::fromRoute('recurly.process_push_notification', [
            'key' => $this->config('recurly.settings')->get('recurly_listener_key'),
          ])->setAbsolute()->toString(),
        ]
      ),
      '#default_value' => $this->config('recurly.settings')->get('recurly_listener_key') ?: '',
      '#required' => TRUE,
      '#size' => 32,
      // @FIXME
      // '#field_prefix' => url('recurly/listener/', ['absolute' => TRUE]),
    ];

    $form['push']['recurly_push_logging'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Log authenticated incoming push notifications. (Primarily used for debugging purposes.)'),
      '#default_value' => $this->config('recurly.settings')->get('recurly_push_logging'),
    ];

    // Get a list of entity types and their bundles.
    $entity_types = $this->entityTypeBundleInfo->getAllBundleInfo();
    $entity_options = [];
    foreach ($entity_types as $entity_name => $bundles) {
      $entity_type = $this->entityTypeManager->getDefinition($entity_name);
      $entity_options[$entity_name] = $entity_type->getLabel();
      $first_bundle_name = key($bundles);
      // Generate a list of bundles only if this entity type has them.
      if (count($bundles) > 1 || $first_bundle_name !== $entity_name) {
        foreach ($bundles as $bundle_name => $bundle_info) {
          $entity_type_options[$entity_name][$bundle_name] = $bundle_info['label'];
        }
      }
    }

    // If any of the below options change we need to rebuild the menu system.
    // Keep a record of their current values.
    $recurly_entity_type = $this->config('recurly.settings')->get('recurly_entity_type');
    $pages_previous_values = [
      'recurly_entity_type' => $recurly_entity_type,
      'recurly_bundle_' . $recurly_entity_type => $this->config('recurly.settings')->get('recurly_bundle_' . $recurly_entity_type),
      'recurly_pages' => $this->config('recurly.settings')->get('recurly_pages') ?: 1,
      'recurly_coupon_page' => $this->config('recurly.settings')->get('recurly_coupon_page') ?: 1,
      'recurly_subscription_plans' => $this->config('recurly.settings')->get('recurly_subscription_plans') ?: [],
      'recurly_subscription_max' => $this->config('recurly.settings')->get('recurly_subscription_max') ?: 1,
    ];
    $form_state->setValue('pages_previous_values', $pages_previous_values);

    $form['sync'] = [
      '#type' => 'details',
      '#title' => $this->t('Recurly account syncing'),
      '#open' => !empty($recurly_entity_type),
      '#description' => $this->t("Each time a particular entity type is updated, you may have information sent to Recurly to keep the contact information kept up-to-date. This can be any entity within Drupal, such as User, Content (Node), Group, etc. It is extremely important to maintain updated contact information in Recurly, as when an account enters the dunning process, the e-mail account in Recurly is the primary contact address."),
    ];
    $form['sync']['recurly_entity_type'] = [
      '#title' => $this->t('Send Recurly account updates for each'),
      '#type' => 'select',
      '#options' => [
        '' => 'Nothing (disabled)',
      ] + $entity_options,
      '#default_value' => $recurly_entity_type,
    ];
    if (!empty($entity_type_options)) {
      foreach ($entity_type_options as $entity_name => $bundles) {
        $form['sync']['recurly_bundles']['recurly_bundle_' . $entity_name] = [
          '#title' => $this->t('Specifically the following @entity type', ['@entity' => $entity_name]),
          '#type' => 'select',
          '#options' => $bundles,
          '#default_value' => $this->config('recurly.settings')->get('recurly_bundle_' . $entity_name),
          '#states' => [
            'visible' => [
              'select[name="recurly_entity_type"]' => ['value' => $entity_name],
            ],
          ],
        ];
      }
    }

    $mapping = $this->recurlyTokenManager->tokenMapping();
    $form['sync']['recurly_token_mapping'] = [
      '#title' => $this->t('Token mappings'),
      '#type' => 'details',
      '#open' => FALSE,
      '#tree' => TRUE,
      '#parents' => [
        'recurly_token_mapping',
      ],
      '#description' => $this->t('Each Recurly account field is displayed below, specify a token that will be used to update the Recurly account each time the object (node or user) is updated. The Recurly "username" field is automatically populated with the object name (for users) or title (for nodes).'),
    ];
    $form['sync']['recurly_token_mapping']['email'] = [
      '#title' => $this->t('Email'),
      '#type' => 'textfield',
      '#default_value' => $mapping['email'],
      '#description' => $this->t('i.e. [user:mail] or [node:author:mail]'),
    ];
    $form['sync']['recurly_token_mapping']['username'] = [
      '#title' => $this->t('Username'),
      '#type' => 'textfield',
      '#default_value' => $mapping['username'],
      '#description' => $this->t('i.e. [user:name] or [node:title]'),
    ];
    $form['sync']['recurly_token_mapping']['first_name'] = [
      '#title' => $this->t('First name'),
      '#type' => 'textfield',
      '#default_value' => $mapping['first_name'],
    ];
    $form['sync']['recurly_token_mapping']['last_name'] = [
      '#title' => $this->t('Last name'),
      '#type' => 'textfield',
      '#default_value' => $mapping['last_name'],
    ];
    $form['sync']['recurly_token_mapping']['company_name'] = [
      '#title' => $this->t('Company'),
      '#type' => 'textfield',
      '#default_value' => $mapping['company_name'],
    ];
    $form['sync']['recurly_token_mapping']['address1'] = [
      '#title' => $this->t('Address line 1'),
      '#type' => 'textfield',
      '#default_value' => $mapping['address1'],
    ];
    $form['sync']['recurly_token_mapping']['address2'] = [
      '#title' => $this->t('Address line 2'),
      '#type' => 'textfield',
      '#default_value' => $mapping['address2'],
    ];
    $form['sync']['recurly_token_mapping']['city'] = [
      '#title' => $this->t('City'),
      '#type' => 'textfield',
      '#default_value' => $mapping['city'],
    ];
    $form['sync']['recurly_token_mapping']['state'] = [
      '#title' => $this->t('State'),
      '#type' => 'textfield',
      '#default_value' => $mapping['state'],
      '#description' => $this->t('Values sent to Recurly must be two-letter abbreviations.'),
    ];
    $form['sync']['recurly_token_mapping']['zip'] = [
      '#title' => $this->t('Zip code'),
      '#type' => 'textfield',
      '#default_value' => $mapping['zip'],
    ];
    $form['sync']['recurly_token_mapping']['country'] = [
      '#title' => $this->t('Country'),
      '#type' => 'select',
      '#default_value' => $mapping['country'],
      '#options' => $this->countryManager->getList(),
      '#empty_option' => $this->t('Select country...'),
    ];
    $form['sync']['recurly_token_mapping']['phone'] = [
      '#title' => $this->t('Phone number'),
      '#type' => 'textfield',
      '#default_value' => $mapping['phone'],
    ];
    $form['sync']['recurly_token_mapping']['help'] = [
      '#theme' => 'token_tree_link',
      '#token_types' => array_keys($entity_options),
      '#global_types' => FALSE,
    ];
    $form['pages'] = [
      '#type' => 'details',
      '#title' => $this->t('Built-in subscription/invoice pages'),
      '#open' => $this->config('recurly.settings')->get('recurly_pages'),
      '#description' => $this->t('The Recurly module provides built-in pages for letting users view their own recent invoices on the site instead of needing to go to the Recurly site. If a companion module is enabled such as the Recurly Hosted Pages or Recurly.js module (both included with this project), appropriate links to update billing information or subscribe will also be displayed on these pages.'),
      '#states' => [
        'visible' => [
          'select[name="recurly_entity_type"]' => [
            ':value' => '',
          ],
        ],
      ],
    ];
    $form['pages']['recurly_pages'] = [
      '#title' => $this->t('Enable built-in pages'),
      '#type' => 'checkbox',
      '#default_value' => $this->config('recurly.settings')->get('recurly_pages'),
      '#description' => $this->t('Hosted pages will be enabled on the same object type as the "Account Syncing" option above.'),
    ];

    // All the settings below are dependent upon the pages option being enabled.
    $pages_enabled = [
      'visible' => [
        'input[name=recurly_pages]' => [
          'checked' => TRUE,
        ],
      ],
    ];
    $form['pages']['recurly_coupon_page'] = [
      '#title' => $this->t('Enable coupon redemption page'),
      '#type' => 'checkbox',
      '#default_value' => $this->config('recurly.settings')->get('recurly_coupon_page') ?: 1,
      '#description' => $this->t('Show the "Redeem coupon" tab underneath the subscription page.'),
      '#states' => $pages_enabled,
    ];
    $form['pages']['recurly_subscription_display'] = [
      '#title' => $this->t('List subscriptions'),
      '#type' => 'radios',
      // @TODO: Convert these array keys to class constants.
      '#options' => [
        'live' => $this->t('Live subscriptions (active, trials, canceled, and past due)'),
        'all' => $this->t('All (includes expired subscriptions)'),
      ],
      '#description' => $this->t('Users may subscribe or switch between any of the enabled plans by visiting the Subscription tab.'),
      '#default_value' => $this->config('recurly.settings')->get('recurly_subscription_display') ?: 'live',
      '#states' => $pages_enabled,
    ];
    $form['pages']['recurly_subscription_max'] = [
      '#title' => $this->t('Multiple plans'),
      '#type' => 'radios',
      // Allows the number of plans to be some arbitrary amount in the future.
      '#options' => [
        '1' => $this->t('Single-plan mode'),
        '0' => $this->t('Multiple-plan mode'),
      ],
      '#description' => $this->t('Single-plan mode allows users are only one subscription at a time, preventing them from having multiple plans active at the same time. If users are allowed to sign up for more than one subscription, use Multiple-plan mode.'),
      '#access' => count($plan_options),
      '#default_value' => $this->config('recurly.settings')->get('recurly_subscription_max'),
      '#states' => $pages_enabled,
    ];
    $form['pages']['recurly_subscription_upgrade_timeframe'] = [
      '#title' => $this->t('Upgrade plan behavior'),
      '#type' => 'radios',
      '#options' => [
        'now' => $this->t('Upgrade immediately (pro-rating billing period usage)'),
        'renewal' => $this->t('On next renewal'),
      ],
      '#access' => count($plan_options) > 1,
      '#description' => $this->t('Affects users who are able to change their own plan (if more than one is enabled). Overriddable when changing plans as users with "Administer Recurly" permission. An upgrade is considered moving to any plan that costs more than the current plan (regardless of billing cycle).'),
      '#default_value' => $this->config('recurly.settings')->get('recurly_subscription_upgrade_timeframe') ?: 'now',
      '#states' => $pages_enabled,
    ];
    $form['pages']['recurly_subscription_downgrade_timeframe'] = [
      '#title' => $this->t('Downgrade plan behavior'),
      '#type' => 'radios',
      '#options' => [
        'now' => $this->t('Downgrade immediately (pro-rating billing period usage)'),
        'renewal' => $this->t('On next renewal'),
      ],
      '#access' => count($plan_options) > 1,
      '#description' => $this->t('Affects users who are able to change their own plan (if more than one is enabled). Overriddable when changing plans as users with "Administer Recurly" permission. A downgrade is considered moving to any plan that costs less than the current plan (regardless of billing cycle).'),
      '#default_value' => $this->config('recurly.settings')->get('recurly_subscription_downgrade_timeframe') ?: 'renewal',
      '#states' => $pages_enabled,
    ];
    $form['pages']['recurly_subscription_cancel_behavior'] = [
      '#title' => $this->t('Cancel plan behavior'),
      '#type' => 'radios',
      '#options' => [
        'cancel' => $this->t('Cancel at renewal (leave active until end of period)'),
        'terminate_prorated' => $this->t('Terminate immediately (prorated refund)'),
        'terminate_full' => $this->t('Terminate immediately (full refund)'),
      ],
      '#description' => $this->t('Affects users canceling their own subscription plans. Overriddable when canceling plans as users with "Administer Recurly" permission. Note that this behavior is also used when content associated with a Recurly account is deleted, or when users associated with an account are canceled.'),
      '#enabled' => count($plan_options) > 1,
      '#default_value' => $this->config('recurly.settings')->get('recurly_subscription_cancel_behavior') ?: 'cancel',
      '#states' => $pages_enabled,
    ];

    $form = parent::buildForm($form, $form_state);
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $keys = [
      'recurly_private_api_key',
      'recurly_public_key',
      'recurly_subdomain',
      'recurly_listener_key',
    ];
    foreach ($keys as $key) {
      $form_state->setValue([$key], trim($form_state->getValue([$key])));
    }

    // Check that the API key is valid.
    if ($form_state->getValue(['recurly_private_api_key'])) {
      try {
        $settings = [
          'api_key' => $form_state->getValue([
            'recurly_private_api_key',
          ]),
          'public_key' => $form_state->getValue(['recurly_public_key']),
          'subdomain' => $form_state->getValue([
            'recurly_subdomain',
          ]),
        ];
        recurly_client_initialize($settings, TRUE);
        $plans = recurly_subscription_plans();
      }

      catch (\Recurly_UnauthorizedError $e) {
        $form_state->setErrorByName('recurly_private_api_key', $this->t('Your API Key is not authorized to connect to Recurly.'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('recurly.settings')
      ->set('recurly_private_api_key', $form_state->getValue('recurly_private_api_key'))
      ->set('recurly_public_key', $form_state->getValue('recurly_public_key'))
      ->set('recurly_subdomain', $form_state->getValue('recurly_subdomain'))
      ->set('recurly_default_currency', $form_state->getValue('recurly_default_currency'))
      ->set('recurly_listener_key', $form_state->getValue('recurly_listener_key'))
      ->set('recurly_push_logging', $form_state->getValue('recurly_push_logging'))
      ->set('recurly_entity_type', $form_state->getValue('recurly_entity_type'))
      ->set('recurly_token_mapping', $form_state->getValue('recurly_token_mapping'))
      ->set('recurly_pages', $form_state->getValue('recurly_pages'))
      ->set('recurly_coupon_page', $form_state->getValue('recurly_coupon_page'))
      ->set('recurly_subscription_display', $form_state->getValue('recurly_subscription_display'))
      ->set('recurly_subscription_max', $form_state->getValue('recurly_subscription_max'))
      ->set('recurly_subscription_upgrade_timeframe', $form_state->getValue('recurly_subscription_upgrade_timeframe'))
      ->set('recurly_subscription_downgrade_timeframe', $form_state->getValue('recurly_subscription_downgrade_timeframe'))
      ->set('recurly_subscription_cancel_behavior', $form_state->getValue('recurly_subscription_cancel_behavior'))
      ->save();

    // Rebuild the menu system if any of the built-in page options change.
    $previous_values = $form_state->get(['pages_previous_values']) ? $form_state->get(['pages_previous_values']) : [];
    foreach ($previous_values as $variable_name => $previous_value) {
      if (!$form_state->getValue([$variable_name]) && $form_state->getValue([$variable_name]) !== $previous_value) {
        $this->routeBuilder->rebuild();
      }
    }

    parent::submitForm($form, $form_state);
  }

}
