<?php

namespace Drupal\recurly\Form;

use Drupal\Core\Form\FormBase;
use Drupal\recurly\RecurlyClient;
use Drupal\recurly\RecurlyFormatManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Parent class for Recurly forms.
 */
abstract class RecurlyNonConfigForm extends FormBase {

  /**
   * The Recurly client service, initialized on construction.
   *
   * @var \Drupal\recurly\RecurlyClient
   */
  protected $recurlyClient;

  /**
   * The formatting service.
   *
   * @var \Drupal\recurly\RecurlyFormatManager
   */
  protected $recurlyFormatter;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('recurly.client'),
      $container->get('recurly.format_manager')
    );
  }

  /**
   * Constructs a \Drupal\recurly\Form\RecurlyRedeemCouponForm object.
   *
   * @param \Drupal\recurly\RecurlyClient $client
   *   The Recurly client service.
   * @param \Drupal\recurly\RecurlyFormatManager $recurly_formatter
   *   A Recurly formatter object.
   */
  public function __construct(
    RecurlyClient $client,
    RecurlyFormatManager $recurly_formatter
  ) {
    $this->recurlyClient = $client;
    $this->recurlyFormatter = $recurly_formatter;
  }

}
