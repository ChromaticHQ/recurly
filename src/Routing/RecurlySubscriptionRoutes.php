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
   * {@inheritdoc}
   *
   * @todo
   * - We need custom access logic converted from D7's
   *   recurly_subscription_page_access() for 'administer recurly' permission.
   * - Subscription tab does not appear on user pages.
   */
  public function routes() {
    // Find out what kind of entity we're dealing with.
    $entity_type = \Drupal::config('recurly.settings')->get('recurly_entity_type');

    // Add and configure the new route.
    $routes = [];
    $routes['recurly.subscription_list'] = new Route(
      "/$entity_type/{id}/subscription",
      [
        '_controller' => '\Drupal\recurly\Controller\RecurlySubscriptionListController::subscriptionList',
        '_title' => 'Subscription Information',
      ],
      ['_permission' => 'administer recurly']
    );

    $routes['recurly.subscription_signup'] = new Route(
      "/$entity_type/{id}/subscription/signup",
      [
        '_controller' => '\Drupal\recurly\Controller\RecurlySubscriptionSelectPlanController::planSelect',
        '_title' => \Drupal::config('recurly.settings')->get('recurly_subscription_max') === '1' ? 'Signup' : 'Add plan',
      ],
      // @FIXME: Permissions callback logic needs to be implemented.
      ['_permission' => 'administer recurly']
    );
    $routes['recurly.subscription_change'] = new Route(
      "/$entity_type/{id}/subscription/change",
      [
        '_controller' => '\Drupal\recurly\Controller\RecurlySubscriptionSelectPlanController::planSelect',
        '_title' => 'Change Plan',
      ],
      ['_permission' => 'administer recurly']
    );

    return $routes;
  }

}
