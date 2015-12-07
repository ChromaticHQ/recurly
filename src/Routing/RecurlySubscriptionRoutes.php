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
    $routes = array();
    $routes['recurly.subscription_list'] = new Route(
      "/$entity_type/{id}/subscription",
      array(
        '_controller' => '\Drupal\recurly\Controller\RecurlySubscriptionListController::subscriptionList',
        '_title' => 'Subscription Information',
      ),
      array('_permission' => 'administer recurly')
    );

    return $routes;
  }

}
