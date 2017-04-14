<?php

namespace Drupal\recurly_hosted\Routing;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Defines dynamic routes.
 */
class RecurlyHostedRouteSubscriber extends RouteSubscriberBase {

  /**
   * The entity manager service.
   *
   * @var \Drupal\Core\Entity\EntityManagerInterface
   */
  protected $entityManager;

  /**
   * The Recurly settings.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $recurlySettings;

  /**
   * Constructs a new RouteSubscriber object.
   *
   * @param \Drupal\Core\Entity\EntityManagerInterface $entity_manager
   *   The entity type manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config service.
   */
  public function __construct(EntityManagerInterface $entity_manager, ConfigFactoryInterface $config_factory) {
    $this->entityManager = $entity_manager;
    $this->recurlySettings = $config_factory->get('recurly.settings');
  }

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    $entity_type = $this->recurlySettings->get('recurly_entity_type');
    if ($entity_type && $this->recurlySettings->get('recurly_pages')) {
      $route = new Route(
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
      $collection->add('recurly_hosted.update_billing', $route);
    }
  }

}
