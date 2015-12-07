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
   * @param int $id
   *   The id of the entity.
   *
   * @return array
   *   Recurly subscription details or a no-results message as a render array.
   */
  public function subscriptionList($id) {
    /* @var \Drupal\Core\Entity\EntityStorageInterface $entity_storage */
    $entity = $this->entityStorage->load($id);

    $content = $entity ? $entity->label() : t('No corresponding entity loaded!');

    return ['#markup' => $content, '#allowed_tags' => Xss::getHtmlTagList()];
  }

}
