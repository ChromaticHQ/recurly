<?php

namespace Drupal\recurly_hosted\Routing;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Route;

/**
 * Defines dynamic routes.
 */
class RecurlyHostedRoutes implements ContainerInjectionInterface {

  /**
   * The Recurly settings.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $recurlySettings;

  /**
   * Constructs a new Recurly hosted routes route subscriber.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config service.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    $this->recurlySettings = $config_factory->get('recurly.settings');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory')
    );
  }

  /**
   * Define custom routes.
   *
   * The below routes are defined here, instead of in
   * recurly_hosted.routing.yml, since they depend on logic in PHP and can not
   * be defined in YAML.
   */
  public function routes() {
    $entity_type = $this->recurlySettings->get('recurly_entity_type');
    if ($entity_type && $this->recurlySettings->get('recurly_pages')) {
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
