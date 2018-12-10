<?php

namespace Drupal\recurlyjs\Form;

use Drupal\Component\EventDispatcher\ContainerAwareEventDispatcher;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Locale\CountryManagerInterface;
use Drupal\recurly\RecurlyClient;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * RecurlyJS abstract class with common form elements to be shared.
 */
abstract class RecurlyJsFormBase extends FormBase {

  /**
   * The country manager service.
   *
   * @var \Drupal\Core\Locale\CountryManagerInterface
   */
  protected $countryManager;

  /**
   * The event dispatcher service.
   *
   * @var \Drupal\Component\EventDispatcher\ContainerAwareEventDispatcher
   */
  protected $eventDispatcher;

  /**
   * The Recurly client service, initialized on construction.
   *
   * @var \Drupal\recurly\RecurlyClient
   */
  protected $recurlyClient;

  /**
   * Creates a RecurlyJS base form.
   *
   * @param \Drupal\Core\Locale\CountryManagerInterface $country_manager
   *   The country manager service.
   * @param \Drupal\Component\EventDispatcher\ContainerAwareEventDispatcher $event_dispatcher
   *   The event dispatcher service.
   * @param \Drupal\recurly\RecurlyClient $client
   *   The Recurly client service.
   */
  public function __construct(
    CountryManagerInterface $country_manager,
    ContainerAwareEventDispatcher $event_dispatcher,
    RecurlyClient $client
  ) {
    $this->countryManager = $country_manager;
    $this->eventDispatcher = $event_dispatcher;
    $this->recurlyClient = $client;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('country_manager'),
      $container->get('event_dispatcher'),
      $container->get('recurly.client')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#attached']['library'][] = 'recurlyjs/recurlyjs.default';
    $form['#attached']['library'][] = 'recurlyjs/recurlyjs.recurlyjs';
    $form['#attached']['library'][] = 'recurlyjs/recurlyjs.element';

    $form['#attached']['drupalSettings']['recurlyjs']['recurly_public_key'] = $this->config('recurly.settings')->get('recurly_public_key') ?: '';
    $form['#attached']['library'][] = 'recurlyjs/recurlyjs.configure';
    // @FIXME: Include inline call to configure RecurlyJS.
    // @see: https://github.com/CHROMATIC-LLC/recurly/blob/7.x-2.x/modules/recurlyjs/includes/recurlyjs.pages.inc#L510-L513
    return $this->appendBillingFields($form);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * Configure Form API elements for Recurly billing forms.
   *
   * @param array $form
   *   A Drupal form array.
   *
   * @return array
   *   The modified form array.
   */
  private function appendBillingFields(array $form) {
    $form['#prefix'] = '<div class="recurly-form-wrapper">';
    $form['#suffix'] = '</div>';

    $form['billing'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Payment Information'),
      '#attributes' => [
        'class' => ['recurlyjs-billing-info'],
      ],
    ];
    // recurly-element.js adds errors here upon failed validation.
    $form['errors'] = [
      '#markup' => '<div id="recurly-form-errors"></div>',
      '#weight' => -300,
    ];
    $form['billing']['name'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['recurlyjs-name-wrapper'],
      ],
    ];
    $form['billing']['name']['first_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('First Name'),
      '#attributes' => [
        'data-recurly' => 'first_name',
      ],
      '#prefix' => '<div class="recurlyjs-form-item__first_name">',
      '#suffix' => '</div>',
    ];
    $form['billing']['name']['last_name'] = [
      '#type' => 'textfield',
      '#title' => t('Last Name'),
      '#attributes' => [
        'data-recurly' => 'last_name',
      ],
      '#after_build' => ['::removeElementName'],
      '#prefix' => '<div class="recurlyjs-form-item__last_name">',
      '#suffix' => '</div>',
    ];
    $form['billing']['cc_info'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['recurlyjjs-cc-info'],
      ],
    ];
    // Credit card fields are represented as <divs> in the DOM and Recurly.JS
    // will dynamically replace them with an input field inside of an iFrame. In
    // order to ensure these fields never contain data in Drupal's Form API we
    // just add them as static markup.
    $form['billing']['cc_info']['number'] = [
      '#title' => $this->t('Card Number'),
      '#markup' => '<label for="number">' . $this->t('Card Number') . '</label><div data-recurly="number"></div>',
      '#allowed_tags' => ['label', 'div'],
      '#prefix' => '<div class="form-item recurlyjs-form-item__number">',
      '#suffix' => '<span class="recurlyjs-icon-card recurlyjs-icon-card__inline recurlyjs-icon-card__unknown"></span></div>',
    ];
    $form['billing']['cc_info']['cvv'] = [
      '#title' => $this->t('CVV'),
      '#markup' => '<label for="cvv">' . t('CVV') . '</label><div data-recurly="cvv"></div>',
      '#allowed_tags' => ['label', 'div'],
      '#prefix' => '<div class="form-item recurlyjs-form-item__cvv">',
      '#suffix' => '</div>',
    ];
    $form['billing']['cc_info']['month'] = [
      '#title' => $this->t('Month'),
      '#markup' => '<label for="month">' . $this->t('Month') . '</label><div data-recurly="month"></div>',
      '#allowed_tags' => ['label', 'div'],
      '#prefix' => '<div class="form-item recurlyjs-form-item__month">',
      '#suffix' => '</div>',
    ];
    $form['billing']['cc_info']['year'] = [
      '#title' => $this->t('Year'),
      '#markup' => '<label for="year">' . $this->t('Year') . '</label><div data-recurly="year"></div>',
      '#allowed_tags' => ['label', 'div'],
      '#prefix' => '<div class="form-item recurlyjs-form-item__year">',
      '#suffix' => '</div>',
    ];

    $address_requirement = \Drupal::state()->get('recurlyjs_address_requirement', 'full');
    $hide_vat_number = \Drupal::state()->get('recurlyjs_hide_vat_number', 0);

    if (in_array($address_requirement, [
      'zipstreet',
      'full',
    ])) {
      $form['billing']['address1'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Address Line 1'),
        '#attributes' => [
          'data-recurly' => 'address1',
        ],
        '#prefix' => '<div class="recurlyjs-form-item__address1">',
        '#suffix' => '</div>',
        '#after_build' => ['::removeElementName'],
      ];
      $form['billing']['address2'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Address Line 2'),
        '#attributes' => [
          'data-recurly' => 'address2',
        ],
        '#prefix' => '<div class="recurlyjs-form-item__address2">',
        '#suffix' => '</div>',
        '#after_build' => ['::removeElementName'],
      ];
    }
    $form['billing']['city_state_postal'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['recurlyjs-city-state-postal-wrapper'],
      ],
    ];

    if ($address_requirement == 'full') {
      $form['billing']['city_state_postal']['city'] = [
        '#type' => 'textfield',
        '#title' => $this->t('City'),
        '#attributes' => [
          'data-recurly' => 'city',
        ],
        '#prefix' => '<div class="recurlyjs-form-item__city">',
        '#suffix' => '</div>',
        '#after_build' => ['::removeElementName'],
      ];
      $form['billing']['city_state_postal']['state'] = [
        '#type' => 'textfield',
        '#title' => $this->t('State'),
        '#attributes' => [
          'data-recurly' => 'state',
        ],
        '#prefix' => '<div class="recurlyjs-form-item__state">',
        '#suffix' => '</div>',
        '#after_build' => ['::removeElementName'],
      ];
    }

    if ($address_requirement != 'none') {
      $form['billing']['city_state_postal']['postal_code'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Postal Code'),
        '#attributes' => [
          'data-recurly' => 'postal_code',
        ],
        '#prefix' => '<div class="recurlyjs-form-item__postal_code">',
        '#suffix' => '</div>',
        '#after_build' => ['::removeElementName'],
      ];
    }

    if ($address_requirement == 'full') {
      $countries = $this->countryManager->getList();
      $form['billing']['country'] = [
        '#type' => 'select',
        '#title' => $this->t('Country'),
        '#attributes' => [
          'data-recurly' => 'country',
        ],
        '#prefix' => '<div class="recurlyjs-form-item__country">',
        '#suffix' => '</div>',
        '#after_build' => ['::removeElementName'],
        '#options' => $countries,
        '#empty_option' => $this->t('Select country...'),
      ];
    }

    if (!$hide_vat_number) {
      $form['billing']['vat_number'] = [
        '#type' => 'textfield',
        '#title' => $this->t('VAT Number'),
        '#attributes' => [
          'data-recurly' => 'vat_number',
        ],
        '#prefix' => '<div class="recurlyjs-form-item__vat_number">',
        '#suffix' => '</div>',
        '#after_build' => ['::removeElementName'],
      ];
    }

    $form['tax_code'] = [
      '#type' => 'hidden',
      '#title' => $this->t('digital'),
      '#attributes' => [
        'data-recurly' => 'tax_code',
      ],
      '#after_build' => ['::removeElementName'],
    ];
    $form['recurly-token'] = [
      '#type' => 'hidden',
      '#attributes' => [
        'data-recurly' => 'token',
      ],
    ];
    return $form;
  }

  /**
   * Element after_build callback to remove the input #name attribute.
   *
   * @see https://docs.recurly.com/js/#build-a-card-form
   */
  public function removeElementName($element, $form_state) {
    unset($element['#name']);
    return $element;
  }

}
