<?php

namespace Drupal\recurly\Routing;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\Core\Routing\RoutingEvents;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Subscriber for Recurly routes.
 */
class RecurlyRouteSubscriber extends RouteSubscriberBase {

  /**
   * The entity manager service.
   *
   * @var \Drupal\Core\Entity\EntityManagerInterface
   */
  protected $entityManager;

  /**
   * The Recurly settings.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $recurlySettings;

  /**
   * The Recurly entity type machine name.
   *
   * @var string
   */
  protected $entityType;

  /**
   * The Recurly entity type definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeInterface
   */
  protected $entityDefinition;

  /**
   * The default route options.
   *
   * @var array
   */
  protected $routeOptions = [];

  /**
   * Constructs a new RouteSubscriber object.
   *
   * @param \Drupal\Core\Entity\EntityManagerInterface $entity_manager
   *   The entity type manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config service.
   */
  public function __construct(EntityManagerInterface $entity_manager, ConfigFactoryInterface $config_factory) {
    $this->entityManager = $entity_manager;
    $this->recurlySettings = $config_factory->get('recurly.settings');
    $entity_manager_definitions = $this->entityManager->getDefinitions();
    $this->entityType = $this->recurlySettings->get('recurly_entity_type');
    $this->entityDefinition = $entity_manager_definitions[$this->entityType];
    // Set shared route options.
    $this->routeOptions = [
      '_admin_route' => TRUE,
      '_recurly_entity_type_id' => $this->entityType,
      'parameters' => [
        $this->entityType => [
          'type' => 'entity:' . $this->entityType,
        ],
      ],
    ];
    // Determine if custom routes should be added.
    $this->addRoutes = $this->addRoutes();
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = parent::getSubscribedEvents();
    $events[RoutingEvents::ALTER] = ['onAlterRoutes', 100];
    return $events;
  }

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    if (!$this->addRoutes) {
      return;
    }

    if ($recurly_subscriptionlist = $this->entityDefinition->getLinkTemplate('recurly-subscriptionlist')) {
      $route = new Route(
        $recurly_subscriptionlist,
        [
          '_controller' => '\Drupal\recurly\Controller\RecurlySubscriptionListController::subscriptionList',
          '_title' => $this->recurlySettings->get('recurly_subscription_max') == 1 ? 'Subscription Summary' : 'Subscription List',
        ],
        [
          '_permission' => 'manage recurly subscription',
          // If the user does not have access to update this entity, they do
          // not have the ability to update the subscription.
          '_entity_access' => "$this->entityType.update",
          '_access_check_recurly_user' => 'TRUE',
          '_access_check_recurly_list' => 'TRUE',
        ],
        $this->routeOptions
      );
      // Give it a name and add to the route collection.
      $collection->add("entity.$this->entityType.recurly_subscriptionlist", $route);
    }

    if ($recurly_change = $this->entityDefinition->getLinkTemplate('recurly-change')) {
      $route = new Route(
        $recurly_change,
        [
          '_controller' => '\Drupal\recurly\Controller\RecurlySubscriptionSelectPlanController::planSelect',
          '_title' => 'Change plan',
          'subscription_id' => 'latest',
        ],
        [
          '_entity_access' => "$this->entityType.update",
          '_access_check_recurly_user' => 'TRUE',
          '_access_check_recurly_local_account' => 'TRUE',
          '_access_check_recurly_change_plan' => 'TRUE',
        ],
        $this->routeOptions
      );
      $collection->add("entity.$this->entityType.recurly_change", $route);
    }

    if ($recurly_planchange = $this->entityDefinition->getLinkTemplate('recurly-planchange')) {
      $route = new Route(
        $recurly_planchange,
        [
          '_controller' => '\Drupal\recurly\Controller\RecurlySubscriptionChangeController::changePlan',
          '_title' => 'Change subscription',
          'operation' => 'change_plan',
        ],
        [
          '_entity_access' => "$this->entityType.update",
          '_access_check_recurly_user' => 'TRUE',
          '_access_check_recurly_local_account' => 'TRUE',
          '_access_check_recurly_change_plan' => 'TRUE',
        ],
        $this->routeOptions
      );
      $collection->add("entity.$this->entityType.recurly_planchange", $route);
    }

    if ($recurly_signup = $this->entityDefinition->getLinkTemplate('recurly-signup')) {
      $route = new Route(
        $recurly_signup,
        [
          '_controller' => '\Drupal\recurly\Controller\RecurlySubscriptionSelectPlanController::planSelect',
          '_title' => $this->recurlySettings->get('recurly_subscription_max') == 1 ? 'Signup' : 'Add plan',
        ],
        [
          '_entity_access' => "$this->entityType.update",
          '_access_check_recurly_user' => 'TRUE',
          '_access_check_recurly_select_plan' => 'TRUE',
        ],
        $this->routeOptions
      );
      // Give it a name and add it to the route collection.
      $collection->add("entity.$this->entityType.recurly_signup", $route);
    }
    if ($recurly_cancel_latest = $this->entityDefinition->getLinkTemplate('recurly-cancellatest')) {
      $route = new Route(
        $recurly_cancel_latest,
        [
          '_controller' => '\Drupal\recurly\Controller\RecurlySubscriptionCancelController::subscriptionCancel',
          '_title' => 'Cancel subscription',
          'subscription_id' => 'latest',
        ],
        [
          '_entity_access' => "$this->entityType.update",
          '_access_check_recurly_user' => 'TRUE',
          '_access_check_recurly_local_account' => 'TRUE',
          '_access_check_recurly_cancel' => 'TRUE',
        ],
        $this->routeOptions
      );

      $collection->add("entity.$this->entityType.recurly_cancellatest", $route);
    }
    if ($recurly_cancel = $this->entityDefinition->getLinkTemplate('recurly-cancel')) {
      $route = new Route(
        $recurly_cancel,
        [
          '_controller' => '\Drupal\recurly\Controller\RecurlySubscriptionCancelController::subscriptionCancel',
          '_title' => 'Cancel subscription',
        ],
        [
          '_entity_access' => "$this->entityType.update",
          '_access_check_recurly_user' => 'TRUE',
          '_access_check_recurly_local_account' => 'TRUE',
        ],
        $this->routeOptions
      );

      $collection->add("entity.$this->entityType.recurly_cancel", $route);
    }

    if ($recurly_reactivate_latest = $this->entityDefinition->getLinkTemplate('recurly-reactivatelatest')) {
      $route = new Route(
        $recurly_reactivate_latest,
        [
          '_controller' => '\Drupal\recurly\Controller\RecurlySubscriptionReactivateController::reactivateSubscription',
          '_title' => 'Reactivate',
        ],
        [
          '_entity_access' => "$this->entityType.update",
          '_access_check_recurly_user' => 'TRUE',
          '_access_check_recurly_local_account' => 'TRUE',
          '_access_check_recurly_reactivate' => 'TRUE',
        ],
        $this->routeOptions
      );

      $collection->add("entity.$this->entityType.recurly_reactivatelatest", $route);
    }
    if ($recurly_reactivate = $this->entityDefinition->getLinkTemplate('recurly-reactivate')) {
      $route = new Route(
        $recurly_reactivate,
        [
          '_controller' => '\Drupal\recurly\Controller\RecurlySubscriptionReactivateController::reactivateSubscription',
          '_title' => 'Reactivate',
        ],
        [
          '_entity_access' => "$this->entityType.update",
          '_access_check_recurly_user' => 'TRUE',
          '_access_check_recurly_local_account' => 'TRUE',
        ],
        $this->routeOptions
      );

      $collection->add("entity.$this->entityType.recurly_reactivate", $route);
    }

    // Invoice routes.
    if ($recurly_invoices = $this->entityDefinition->getLinkTemplate('recurly-invoices')) {
      $route = new Route(
        $recurly_invoices,
        [
          '_controller' => '\Drupal\recurly\Controller\RecurlyInvoicesController::invoicesList',
          '_title' => 'Invoices',
          'operation' => 'invoices',
        ],
        [
          '_entity_access' => "$this->entityType.update",
          '_access_check_recurly_user' => 'TRUE',
          '_access_check_recurly_local_account' => 'TRUE',
        ],
        $this->routeOptions
      );

      $collection->add("entity.$this->entityType.recurly_invoices", $route);
    }
    if ($recurly_invoice = $this->entityDefinition->getLinkTemplate('recurly-invoice')) {
      $route = new Route(
        $recurly_invoice,
        [
          '_controller' => '\Drupal\recurly\Controller\RecurlyInvoicesController::getInvoice',
          '_title' => 'Invoice',
          'operation' => 'invoices',
        ],
        [
          '_entity_access' => "$this->entityType.update",
          '_access_check_recurly_user' => 'TRUE',
          '_access_check_recurly_local_account' => 'TRUE',
        ],
        $this->routeOptions
      );

      $collection->add("entity.$this->entityType.recurly_invoice", $route);
    }
    if ($recurly_invoice_pdf = $this->entityDefinition->getLinkTemplate('recurly-invoicepdf')) {
      $route = new Route(
        $recurly_invoice_pdf,
        [
          '_controller' => '\Drupal\recurly\Controller\RecurlyInvoicesController::getInvoicePdf',
          '_title' => 'Invoice PDF',
          'operation' => 'invoices',
        ],
        [
          '_entity_access' => "$this->entityType.update",
          '_access_check_recurly_user' => 'TRUE',
          '_access_check_recurly_local_account' => 'TRUE',
        ],
        $this->routeOptions
      );

      $collection->add("entity.$this->entityType.recurly_invoicepdf", $route);
    }
    if ($this->recurlySettings->get('recurly_coupon_page') ?: 1) {
      if ($recurly_coupon = $this->entityDefinition->getLinkTemplate('recurly-coupon')) {
        $route = new Route(
          $recurly_coupon,
          [
            '_form' => '\Drupal\recurly\Form\RecurlyRedeemCouponForm',
            '_title' => 'Redeem coupon',
            'operation' => 'update',
          ],
          [
            '_entity_access' => "$this->entityType.update",
            '_access_check_recurly_user' => 'TRUE',
            '_access_check_recurly_local_account' => 'TRUE',
          ],
          $this->routeOptions
        );

        $collection->add("entity.$this->entityType.recurly_coupon", $route);
      }
    }
  }

  /**
   * Determines if custom routes should be added.
   *
   * @return bool
   *   Boolean indicating if custom routes should be created.
   */
  protected function addRoutes() {
    if ($this->entityDefinition->hasLinkTemplate('recurly-subscriptionlist')) {
      return TRUE;
    }
    if ($this->entityDefinition->hasLinkTemplate('recurly-signup')) {
      return TRUE;
    }
    if ($this->entityDefinition->hasLinkTemplate('recurly-change')) {
      return TRUE;
    }
    if ($this->entityDefinition->hasLinkTemplate('recurly-billing')) {
      return TRUE;
    }

    return FALSE;
  }

}
