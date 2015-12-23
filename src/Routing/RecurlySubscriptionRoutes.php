<?php

/**
 * @file
 * Contains \Drupal\recurly\Routing\RecurlySubscriptionRoutes.
 */

namespace Drupal\recurly\Routing;

use Symfony\Component\Routing\Route;

/**
 * Defines dynamic routes.
 */
class RecurlySubscriptionRoutes {

  /**
   * Define custom routes.
   *
   * The below routes are defined here, instead of in recurly.routing.yml,
   * since they depend on logic in PHP and can not be defined in YAML.
   */
  public function routes() {
    // Find out what kind of entity we're dealing with.
    $entity_type = \Drupal::config('recurly.settings')->get('recurly_entity_type') ?: 'user';

    // Add and configure the new route.
    $routes = [];
    $routes['recurly.subscription_list'] = new Route(
      "/$entity_type/{entity}/subscription",
      [
        '_controller' => '\Drupal\recurly\Controller\RecurlySubscriptionListController::subscriptionList',
        '_title' => 'Subscription Information',
        'operation' => 'list',
      ],
      [
        '_entity_access' => 'entity.update',
        '_access_check_recurly_user' => 'TRUE',
        '_access_check_recurly_list' => 'TRUE',
      ],
      ['parameters' => ['entity' => ['type' => 'entity:' . $entity_type]]]
    );
    $routes['recurly.subscription_signup'] = new Route(
      "/$entity_type/{entity}/subscription/signup",
      [
        '_controller' => '\Drupal\recurly\Controller\RecurlySubscriptionSelectPlanController::planSelect',
        '_title' => \Drupal::config('recurly.settings')->get('recurly_subscription_max') === '1' ? 'Signup' : 'Add plan',
        'operation' => 'select_plan',
      ],
      [
        '_entity_access' => 'entity.update',
        '_access_check_recurly_user' => 'TRUE',
        '_access_check_recurly_default' => 'TRUE',
      ],
      ['parameters' => ['entity' => ['type' => 'entity:' . $entity_type]]]
    );
    $routes['recurly.subscription_plan_select'] = new Route(
      "/$entity_type/{entity}/subscription/change",
      [
        '_controller' => '\Drupal\recurly\Controller\RecurlySubscriptionSelectPlanController::planSelect',
        '_title' => 'Change Plan',
        'subscription_id' => 'latest',
        'operation' => 'change_plan_latest',
      ],
      [
        '_entity_access' => 'entity.update',
        '_access_check_recurly_user' => 'TRUE',
        '_access_check_recurly_default' => 'TRUE',
      ],
      ['parameters' => ['entity' => ['type' => 'entity:' . $entity_type]]]
    );

    // Cancel routes.
    $routes['recurly.subscription_cancel_latest'] = new Route(
      "$entity_type/{entity}/subscription/cancel",
      [
        '_controller' => '\Drupal\recurly\Controller\RecurlySubscriptionCancelController::subscriptionCancel',
        '_title' => 'Cancel subscription',
        'subscription_id' => 'latest',
        'operation' => 'cancel_latest',
      ],
      [
        '_entity_access' => 'entity.update',
        '_access_check_recurly_user' => 'TRUE',
        '_access_check_recurly_default' => 'TRUE',
      ],
      ['parameters' => ['entity' => ['type' => 'entity:' . $entity_type]]]
    );
    $routes['recurly.subscription_cancel'] = new Route(
      "$entity_type/{entity}/subscription/id/{subscription_id}/cancel",
      [
        '_controller' => '\Drupal\recurly\Controller\RecurlySubscriptionCancelController::subscriptionCancel',
        '_title' => 'Cancel subscription',
        'operation' => 'cancel',
      ],
      [
        '_entity_access' => 'entity.update',
        '_access_check_recurly_user' => 'TRUE',
        '_access_check_recurly_default' => 'TRUE',
      ],
      ['parameters' => ['entity' => ['type' => 'entity:' . $entity_type]]]
    );

    // Change routes.
    $routes['recurly.subscription_change'] = new Route(
      "$entity_type/{entity}/subscription/change",
      [
        '_controller' => '\Drupal\recurly\Controller\RecurlySubscriptionSelectPlanController::planSelect',
        '_title' => 'Change plan',
        'subscription_id' => 'latest',
        'operation' => 'change_plan_latest',
      ],
      [
        '_entity_access' => 'entity.update',
        '_access_check_recurly_user' => 'TRUE',
        '_access_check_recurly_default' => 'TRUE',
      ],
      ['parameters' => ['entity' => ['type' => 'entity:' . $entity_type]]]
    );
    $routes['recurly.subscription_plan_change'] = new Route(
      "$entity_type/{entity}/subscription/id/{subscription_id}/change/{new_plan_code}",
      [
        '_controller' => '\Drupal\recurly\Controller\RecurlySubscriptionChangeController::changePlan',
        '_title' => 'Change subscription',
        'operation' => 'change_plan',
      ],
      [
        '_entity_access' => 'entity.update',
        '_access_check_recurly_user' => 'TRUE',
        '_access_check_recurly_default' => 'TRUE',
      ],
      ['parameters' => ['entity' => ['type' => 'entity:' . $entity_type]]]
    );

    // Invoice routes.
    $routes['recurly.subscription_invoices'] = new Route(
      "$entity_type/{entity}/subscription/invoices",
      [
        '_controller' => '\Drupal\recurly\Controller\RecurlyInvoicesController::invoicesList',
        '_title' => 'Invoices',
        'operation' => 'invoices',
      ],
      // @FIXME: Add permission check for access to the specified entity.
      ['_access_check_recurly_user' => 'TRUE'],
      ['_access_check_recurly_default' => 'TRUE'],
      ['parameters' => ['entity' => ['type' => 'entity:' . $entity_type]]]
    );
    $routes['recurly.subscription_invoice'] = new Route(
      "$entity_type/{entity}/subscription/invoices/{invoice_number}",
      [
        '_controller' => '\Drupal\recurly\Controller\RecurlyInvoicesController::getInvoice',
        '_title' => 'Invoice',
        'operation' => 'invoices',
      ],
      // @FIXME: Add permission check for access to the specified entity.
      ['_access_check_recurly_user' => 'TRUE'],
      ['_access_check_recurly_default' => 'TRUE'],
      ['parameters' => ['entity' => ['type' => 'entity:' . $entity_type]]]
    );
    $routes['recurly.subscription_invoice_pdf'] = new Route(
      "$entity_type/{entity}/subscription/invoices/{invoice_number}/pdf",
      [
        '_controller' => '\Drupal\recurly\Controller\RecurlyInvoicesController::getInvoicePdf',
        '_title' => 'Invoice PDF',
        'operation' => 'invoices',
      ],
      // @FIXME: Add permission check for access to the specified entity.
      ['_access_check_recurly_user' => 'TRUE'],
      ['_access_check_recurly_default' => 'TRUE'],
      ['parameters' => ['entity' => ['type' => 'entity:' . $entity_type]]]
    );

    // Reactivate routes.
    $routes['recurly.subscription_reactivate_latest'] = new Route(
      "$entity_type/{entity}/subscription/reactivate",
      [
        '_controller' => '\Drupal\recurly\Controller\RecurlySubscriptionReactivateController::reactivateSubscription',
        '_title' => 'Reactivate',
        'operation' => 'reactivate_latest',
      ],
      [
        '_entity_access' => 'entity.update',
        '_access_check_recurly_user' => 'TRUE',
        '_access_check_recurly_default' => 'TRUE',
      ],
      ['parameters' => ['entity' => ['type' => 'entity:' . $entity_type]]]
    );
    $routes['recurly.subscription_reactivate'] = new Route(
      "$entity_type/{entity}/subscription/id/{subscription_id}/reactivate",
      [
        '_controller' => '\Drupal\recurly\Controller\RecurlySubscriptionReactivateController::reactivateSubscription',
        '_title' => 'Reactivate',
        'operation' => 'reactivate',
      ],
      [
        '_entity_access' => 'entity.update',
        '_access_check_recurly_user' => 'TRUE',
        '_access_check_recurly_default' => 'TRUE',
      ],
      ['parameters' => ['entity' => ['type' => 'entity:' . $entity_type]]]
    );

    if (\Drupal::config('recurly.settings')->get('recurly_coupon_page') ?: 1) {
      $routes['recurly.redeem_coupon'] = new Route(
        "$entity_type/{entity}/subscription/redeem-coupon",
        [
          '_form' => '\Drupal\recurly\Form\RecurlyRedeemCouponForm',
          '_title' => 'Redeem coupon',
          'operation' => 'update',
        ],
        [
          '_entity_access' => 'entity.update',
          '_access_check_recurly_user' => 'TRUE',
          '_access_check_recurly_default' => 'TRUE',
        ],
        ['parameters' => ['entity' => ['type' => 'entity:' . $entity_type]]]
      );
    }
    return $routes;
  }

}
