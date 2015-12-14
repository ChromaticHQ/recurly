<?php

/**
 * @file
 * Contains \Drupal\recurly\Controller\RecurlySubscriptionListController.
 */

namespace Drupal\recurly\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Component\Utility\Xss;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Returns responses for Recurly Subscription List.
 */
class RecurlySubscriptionListController extends ControllerBase {

  /**
   * The user storage.
   *
   * @var \Drupal\user\UserStorageInterface
   */
  protected $entityStorage;

  /**
   * Constructs a RecurlySubscriptionListController object.
   *
   * @param \Drupal\Core\Entity\EntityStorageInterface $entity_storage
   *   The entity storage.
   */
  public function __construct(EntityStorageInterface $entity_storage) {
    $this->entityStorage = $entity_storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $entity_type = \Drupal::config('recurly.settings')->get('recurly_entity_type');

    return new static(
      $container->get('entity.manager')->getStorage($entity_type)
    );
  }

  /**
   * Route title callback.
   *
   * @param int $entity_id
   *   The id of the entity.
   *
   * @return array
   *   Recurly subscription details or a no-results message as a render array.
   */
  public function subscriptionList($entity_id) {
    /* @var \Drupal\Core\Entity\EntityStorageInterface $entity_storage */
    $entity = $this->entityStorage->load($entity_id);

    $subscriptions = [];
    // Initialize the Recurly client with the site-wide settings.
    if (!recurly_client_initialize()) {
      return t('Could not initialize the Recurly client.');
    }
    // Load the account information. This should already be cached by the access
    // check to this page by recurly_subscription_page_access().
    $entity_type = $entity->getEntityType()->getLowercaseLabel();
    $account = recurly_account_load(['entity_type' => $entity_type, 'entity_id' => $entity->id()]);
    // If the user does not have an account yet, send them to the signup page.
    if (empty($account)) {
      if ($url = recurly_url('select_plan', array('entity_type' => $entity_type, 'entity' => $entity))) {
        return $this->redirect($url->getRouteName(), $url->getRouteParameters());
      }
      else {
        throw new NotFoundHttpException();
      }
    }
  }
}
