<?php

namespace Drupal\recurly_hosted\Routing;

use Drupal\recurly\Routing\RecurlyRouteSubscriber;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Defines dynamic routes.
 */
class RecurlyHostedRouteSubscriber extends RecurlyRouteSubscriber {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    if (!$this->addRoutes) {
      return;
    }

    if ($recurly_updatebilling = $this->entityDefinition->getLinkTemplate('recurly-updatebilling')) {
      $route = new Route(
        $recurly_updatebilling,
        [
          '_controller' => '\Drupal\recurly_hosted\Controller\RecurlyHostedAccountRedirectController::redirectToAccountManagement',
          '_title' => 'Update billing information',
          'operation' => 'update_billing',
        ],
        [
          '_entity_access' => "$this->entityType.update",
          '_access_check_recurly_user' => 'TRUE',
          '_access_check_recurly_default' => 'TRUE',
        ],
        $this->routeOptions
      );
      $collection->add('recurly_hosted.update_billing', $route);
    }
  }

}
