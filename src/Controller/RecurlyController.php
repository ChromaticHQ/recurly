<?php

namespace Drupal\recurly\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\recurly\RecurlyClient;
use Drupal\recurly\RecurlyFormatManager;
use Drupal\recurly\RecurlyPagerManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for Recurly controllers.
 */
abstract class RecurlyController extends ControllerBase {

  /**
   * The Recurly page manager service.
   *
   * @var \Drupal\recurly\RecurlyPagerManager
   */
  protected $recurlyPageManager;

  /**
   * The Recurly formatting service.
   *
   * @var \Drupal\recurly\RecurlyFormatManager
   */
  protected $recurlyFormatter;

  /**
   * The Recurly client service, initialized on construction.
   *
   * @var \Drupal\recurly\RecurlyClient
   */
  protected $recurlyClient;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('recurly.pager_manager'),
      $container->get('recurly.format_manager'),
      $container->get('recurly.client')
    );
  }

  /**
   * Class constructor.
   *
   * @param \Drupal\recurly\RecurlyPagerManager $recurly_page_manager
   *   The Recurly page manager service.
   * @param \Drupal\recurly\RecurlyFormatManager $recurly_formatter
   *   The Recurly formatter to be used for formatting.
   * @param \Drupal\recurly\RecurlyClient $client
   *   The Recurly client service.
   */
  public function __construct(RecurlyPagerManager $recurly_page_manager, RecurlyFormatManager $recurly_formatter, RecurlyClient $client) {
    $this->recurlyPageManager = $recurly_page_manager;
    $this->recurlyFormatter = $recurly_formatter;
    $this->recurlyClient = $client;
  }

}
