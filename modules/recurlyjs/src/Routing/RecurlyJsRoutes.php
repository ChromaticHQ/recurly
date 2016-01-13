<?php

/**
 * @file
 * Contains \Drupal\recurlyjs\Routing\RecurlyJsRoutes.
 */

namespace Drupal\recurlyjs\Routing;

use Symfony\Component\Routing\Route;

/**
 * Defines dynamic routes.
 */
class RecurlyJsRoutes {

  /**
   * {@inheritdoc}
   */
  public function routes() {
    $routes = [];

    // Find out what kind of entity we're dealing with.
    $entity_type = \Drupal::config('recurly.settings')->get('recurly_entity_type');

    if ($entity_type && \Drupal::config('recurly.settings')->get('recurly_pages')) {
      $routes['recurlyjs.update_billing'] = new Route(
        "/$entity_type/{entity}/subscription/billing",
        [
          '_form' => '\Drupal\recurlyjs\Form\RecurlyJsUpdateBillingForm',
          '_title' => 'Update billing information',
          'operation' => 'update_billing',
        ],
        [
          '_entity_access' => 'entity.update',
          '_access_check_recurly_user' => 'TRUE',
          '_access_check_recurly_default' => 'TRUE',
        ],
        ['parameters' => ['entity' => ['type' => 'entity:' . $entity_type]]]
      );

      $routes['recurlyjs.subscription_signup'] = new Route(
        "/$entity_type/{entity}/subscription/signup/{plan_code}",
        [
          '_controller' => '\Drupal\recurlyjs\Controller\RecurlyJsSubscriptionSignupController::subscribe',
          '_title' => 'Signup',
          'operation' => 'signup',
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
