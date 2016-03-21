<?php

/**
 * @file
 * Contains \Drupal\recurlyjs\Form\RecurlyJsFormBase.
 */

namespace Drupal\recurlyjs\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * RecurlyJS abstract class with common form elements to be shared.
 */
abstract class RecurlyJsFormBase extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#attached']['library'][] = 'recurlyjs/recurlyjs.recurlyjs';
    $form['#attached']['library'][] = 'recurlyjs/recurlyjs.element';

    $form['#attached']['drupalSettings']['recurlyjs']['recurly_public_key'] = \Drupal::config('recurly.settings')->get('recurly_public_key') ?: '';
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
  private function appendBillingFields($form) {
    $form['#prefix'] = '<div class="recurly-form-wrapper">';
    $form['#suffix'] = '</div>';

    // recurly-element.js adds errors here upon failed validation.
    $form['errors'] = [
      '#markup' => '<div id="recurly-form-errors"></div>',
      '#weight' => -300,
    ];

    $form['first_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('First Name'),
      '#attributes' => [
        'data-recurly' => 'first_name',
      ],
      '#after_build' => ['::removeElementName'],
      '#weight' => -150,
    ];
    $form['last_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Last Name'),
      '#attributes' => [
        'data-recurly' => 'last_name',
      ],
      '#after_build' => ['::removeElementName'],
      '#weight' => -140,
    ];
    $form['address1'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Address Line 1'),
      '#attributes' => [
        'data-recurly' => 'address1',
      ],
      '#after_build' => ['::removeElementName'],
      '#weight' => -130,
    ];
    $form['address2'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Address Line 2'),
      '#attributes' => [
        'data-recurly' => 'address2',
      ],
      '#after_build' => ['::removeElementName'],
      '#weight' => -120,
    ];
    $form['city'] = [
      '#type' => 'textfield',
      '#title' => $this->t('City'),
      '#attributes' => [
        'data-recurly' => 'city',
      ],
      '#after_build' => ['::removeElementName'],
      '#weight' => -110,
    ];
    $form['state'] = [
      '#type' => 'textfield',
      '#title' => $this->t('State'),
      '#attributes' => [
        'data-recurly' => 'state',
      ],
      '#after_build' => ['::removeElementName'],
      '#weight' => -100,
    ];
    $form['postal_code'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Postal Code'),
      '#attributes' => [
        'data-recurly' => 'postal_code',
      ],
      '#after_build' => ['::removeElementName'],
      '#weight' => -90,
    ];
    $countries = \Drupal::service('country_manager')->getList();
    $form['country'] = [
      '#type' => 'select',
      '#title' => $this->t('Country'),
      '#attributes' => [
        'data-recurly' => 'country',
      ],
      '#after_build' => ['::removeElementName'],
      '#weight' => -80,
      '#options' => $countries,
    ];
    $form['vat_number'] = [
      '#type' => 'textfield',
      '#title' => $this->t('VAT Number'),
      '#attributes' => [
        'data-recurly' => 'vat_number',
      ],
      '#after_build' => ['::removeElementName'],
      '#weight' => -70,
    ];
    $form['number'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Card Number'),
      '#attributes' => [
        'data-recurly' => 'number',
      ],
      '#after_build' => ['::removeElementName'],
      '#weight' => -60,
    ];
    $form['cvv'] = [
      '#type' => 'textfield',
      '#title' => $this->t('CVV'),
      '#attributes' => [
        'data-recurly' => 'cvv',
      ],
      '#after_build' => ['::removeElementName'],
      '#weight' => -55,
    ];
    $form['month'] = [
      '#type' => 'textfield',
      '#title' => $this->t('MM'),
      '#attributes' => [
        'data-recurly' => 'month',
      ],
      '#after_build' => ['::removeElementName'],
      '#weight' => -50,
    ];
    $form['year'] = [
      '#type' => 'textfield',
      '#title' => $this->t('YYYY'),
      '#attributes' => [
        'data-recurly' => 'year',
      ],
      '#after_build' => ['::removeElementName'],
      '#weight' => -40,
    ];
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
