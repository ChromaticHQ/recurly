<?php

/**
 * @file
 * Contains \Drupal\recurly_hosted\Routing\RecurlyHostedRoutes.
 */

namespace Drupal\recurly_hosted\Routing;

use Symfony\Component\Routing\Route;

/**
 * Defines dynamic routes.
 */
class RecurlyHostedRoutes {

  /**
   * Define custom routes.
   *
   * The below routes are defined here, instead of in
   * recurly_hosted.routing.yml, since they depend on logic in PHP and can not
   * be defined in YAML.
   */
  public function routes() {
    $entity_type = \Drupal::config('recurly.settings')->get('recurly_entity_type') ?: 'user';
    if ($entity_type && \Drupal::config('recurly.settings')->get('recurly_pages')) {
      $routes = [];

      $routes['recurly_hosted.update_billing'] = new Route(
        "/$entity_type/{entity}/subscription/billing",
        [
          '_controller' => '\Drupal\recurly_hosted\Controller\RecurlyHostedAccountRedirectController::redirectToAccountManagement',
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
      return $routes;
    }

  }

}
