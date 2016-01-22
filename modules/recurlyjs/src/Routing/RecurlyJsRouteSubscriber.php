<?php

/**
 * @file
 * Contains \Drupal\recurlyjs\Routing\RecurlyJsRouteSubscriber.
 */

namespace Drupal\recurlyjs\Routing;

use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\Core\Routing\RoutingEvents;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Subscriber for Recurly routes.
 */
class RecurlyJsRouteSubscriber extends RouteSubscriberBase {

  /**
   * The entity manager service.
   *
   * @var \Drupal\Core\Entity\EntityManagerInterface
   */
  protected $entityManager;

  /**
   * Constructs a new RouteSubscriber object.
   *
   * @param \Drupal\Core\Entity\EntityManagerInterface $entity_manager
   *   The entity type manager.
   */
  public function __construct(EntityManagerInterface $entity_manager) {
    $this->entityManager = $entity_manager;
  }

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    $entity_type_id = \Drupal::config('recurly.settings')->get('recurly_entity_type') ?: 'user';
    $entity_manager_definitions = $this->entityManager->getDefinitions();
    $entity_type = $entity_manager_definitions[$entity_type_id];
    $options = array(
      '_admin_route' => TRUE,
      '_recurly_entity_type_id' => $entity_type_id,
      'parameters' => array(
        $entity_type_id => array(
          'type' => 'entity:' . $entity_type_id,
        ),
      ),
    );
    if ($recurlyjs_signup = $entity_type->getLinkTemplate('recurlyjs-signup')) {
      // Create the route object.
      $route = new Route(
        $recurlyjs_signup,
        array(
          '_controller' => '\Drupal\recurlyjs\Controller\RecurlyJsSubscriptionSignupController::subscribe',
          '_title' => 'Signup',
          'operation' => 'signup',
        ),
        array(
          '_entity_access' => "$entity_type_id.update",
          '_access_check_recurly_user' => 'TRUE',
          '_access_check_recurly_list' => 'TRUE',
        ),
        $options
      );
      // Give it a name and add it to the route collection.
      $collection->add("entity.$entity_type_id.recurlyjs_signup", $route);
    }
    if ($recurlyjs_billing = $entity_type->getLinkTemplate('recurlyjs-billing')) {
      $route = new Route(
        $recurlyjs_billing,
        array(
          '_form' => '\Drupal\recurlyjs\Form\RecurlyJsUpdateBillingForm',
          '_title' => 'Update billing information',
          'operation' => 'update_billing',
        ),
        array(
          '_entity_access' => "$entity_type_id.update",
          '_access_check_recurly_user' => 'TRUE',
          '_access_check_recurly_list' => 'TRUE',
        ),
        $options
      );

      $collection->add("entity.$entity_type_id.recurlyjs_billing", $route);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = parent::getSubscribedEvents();
    $events[RoutingEvents::ALTER] = array('onAlterRoutes', 100);
    return $events;
  }

}
