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
    $entity_type = \Drupal::config('recurly.settings')->get('recurly_entity_type');

    // Add and configure the new route.
    $routes = [];
    $routes['recurly.subscription_list'] = new Route(
      "/$entity_type/{entity}/subscription",
      [
        '_controller' => '\Drupal\recurly\Controller\RecurlySubscriptionListController::subscriptionList',
        '_title' => 'Subscription Information',
      ],
      ['_permission' => 'administer recurly'],
      ['parameters' => ['entity' => ['type' => 'entity:' . $entity_type]]]
    );

    $routes['recurly.subscription_signup'] = new Route(
      "/$entity_type/{entity}/subscription/signup",
      [
        '_controller' => '\Drupal\recurly\Controller\RecurlySubscriptionSelectPlanController::planSelect',
        '_title' => \Drupal::config('recurly.settings')->get('recurly_subscription_max') === '1' ? 'Signup' : 'Add plan',
      ],
      // @FIXME: Permissions callback logic needs to be implemented.
      ['_permission' => 'administer recurly'],
      ['parameters' => ['entity' => ['type' => 'entity:' . $entity_type]]]
    );
    $routes['recurly.subscription_change'] = new Route(
      "/$entity_type/{entity}/subscription/change",
      [
        '_controller' => '\Drupal\recurly\Controller\RecurlySubscriptionSelectPlanController::planSelect',
        '_title' => 'Change Plan',
      ],
      ['_permission' => 'administer recurly'],
      ['parameters' => ['entity' => ['type' => 'entity:' . $entity_type]]]
    );

    return $routes;
  }

}
