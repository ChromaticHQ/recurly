<?php

namespace Drupal\recurly\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Locale\CountryManagerInterface;
use Drupal\Core\Routing\RouteBuilderInterface;
use Drupal\recurly\RecurlyClient;
use Drupal\recurly\RecurlyFormatManager;
use Drupal\recurly\RecurlyTokenManager;
use Drupal\recurly\RecurlyUrlManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Parent class for Recurly configuration forms.
 */
abstract class RecurlyConfigForm extends ConfigFormBase {

  /**
   * The Recurly client service, initialized on construction.
   *
   * @var \Drupal\recurly\RecurlyClient
   */
  protected $recurlyClient;

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
   * The formatting service.
   *
   * @var \Drupal\recurly\RecurlyFormatManager
   */
  protected $recurlyFormatter;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

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
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('recurly.client'),
      $container->get('recurly.url_manager'),
      $container->get('recurly.token_manager'),
      $container->get('recurly.format_manager'),
      $container->get('module_handler'),
      $container->get('entity_type.bundle.info'),
      $container->get('entity_type.manager'),
      $container->get('country_manager'),
      $container->get('router.builder')
    );
  }

  /**
   * Creates a Recurly settings form.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\recurly\RecurlyClient $client
   *   The Recurly client service.
   * @param \Drupal\recurly\RecurlyUrlManager $recurly_url_manager
   *   The Recurly URL manager service.
   * @param \Drupal\recurly\RecurlyTokenManager $recurly_token_manager
   *   The Recurly token manager service.
   * @param \Drupal\recurly\RecurlyFormatManager $recurly_formatter
   *   The Recurly formatter to be used for formatting.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
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
    ConfigFactoryInterface $config_factory,
    RecurlyClient $client,
    RecurlyUrlManager $recurly_url_manager,
    RecurlyTokenManager $recurly_token_manager,
    RecurlyFormatManager $recurly_formatter,
    ModuleHandlerInterface $module_handler,
    EntityTypeBundleInfoInterface $entity_bundle_info,
    EntityTypeManagerInterface $entity_type_manager,
    CountryManagerInterface $country_manager,
    RouteBuilderInterface $route_builder
  ) {
    parent::__construct($config_factory);
    $this->recurlyClient = $client;
    $this->recurlyUrlManager = $recurly_url_manager;
    $this->recurlyTokenManager = $recurly_token_manager;
    $this->recurlyFormatter = $recurly_formatter;
    $this->moduleHandler = $module_handler;
    $this->entityTypeBundleInfo = $entity_bundle_info;
    $this->entityTypeManager = $entity_type_manager;
    $this->countryManager = $country_manager;
    $this->routeBuilder = $route_builder;
  }

}
