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
        '_access_check_recurly' => 'TRUE',
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
        '_access_check_recurly' => 'TRUE',
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
        '_access_check_recurly' => 'TRUE',
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
        '_access_check_recurly' => 'TRUE',
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
        '_access_check_recurly' => 'TRUE',
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
        '_access_check_recurly' => 'TRUE',
      ],
      ['parameters' => ['entity' => ['type' => 'entity:' . $entity_type]]]
    );
    return $routes;
  }

}
