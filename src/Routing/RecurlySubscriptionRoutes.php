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
      ],
      // @FIXME: Add permission check for access to the specified entity.
      ['_permission' => 'administer recurly'],
      ['parameters' => ['entity' => ['type' => 'entity:' . $entity_type]]]
    );
    $routes['recurly.subscription_signup'] = new Route(
      "/$entity_type/{entity}/subscription/signup",
      [
        '_controller' => '\Drupal\recurly\Controller\RecurlySubscriptionSelectPlanController::planSelect',
        '_title' => \Drupal::config('recurly.settings')->get('recurly_subscription_max') === '1' ? 'Signup' : 'Add plan',
        'operation' => 'select_plan',
      ],
      // @FIXME: Add permission check for access to the specified entity.
      ['_access_check_recurly' => 'TRUE'],
      ['parameters' => ['entity' => ['type' => 'entity:' . $entity_type]]]
    );
    $routes['recurly.subscription_change'] = new Route(
      "/$entity_type/{entity}/subscription/change",
      [
        '_controller' => '\Drupal\recurly\Controller\RecurlySubscriptionSelectPlanController::planSelect',
        '_title' => 'Change Plan',
      ],
      // @FIXME: Add permission check for access to the specified entity.
      ['_permission' => 'administer recurly'],
      ['parameters' => ['entity' => ['type' => 'entity:' . $entity_type]]]
    );
    return $routes;
  }

}
