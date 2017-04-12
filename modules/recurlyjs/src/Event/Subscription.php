<?php

namespace Drupal\recurlyjs\Event;

use Drupal\Core\Entity\EntityInterface;
use Symfony\Component\EventDispatcher\Event;

/**
 * Recurly subscription event.
 */
class Subscription extends Event {

  /**
   * The Recurly subscription.
   *
   * @var \Recurly_Subscription
   */
  protected $subscription;

  /**
   * The entity the subscription is attached to.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected $entity;

  /**
   * The current plan code.
   *
   * @var string
   */
  protected $planCode;

  /**
   * Builds a Recurly subscription event.
   *
   * @param \Recurly_Subscription $subscription
   *   The Recurly subscription.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity the subscription is attached to.
   * @param string $plan_code
   *   The subscription plan code.
   */
  public function __construct(
    \Recurly_Subscription $subscription,
    EntityInterface $entity,
    $plan_code) {
    $this->subscription = $subscription;
    $this->entity = $entity;
    $this->planCode = $plan_code;
  }

  /**
   * Gets the subscription acted upon.
   *
   * @return \Recurly_Subscription
   *   The Recurly subscription.
   */
  public function getSubscription() {
    return $this->subscription;
  }

  /**
   * Updates the subscription.
   *
   * @param \Recurly_Subscription $subscription
   *   The Recurly subscription.
   */
  public function updateSubscription(\Recurly_Subscription $subscription) {
    $this->subscription = $subscription;
  }

  /**
   * Gets the entity associated with the subscription.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The entity associated with the subscription.
   */
  public function getEntity() {
    return $this->entity;
  }

  /**
   * Gets the subscription plan code.
   *
   * @return string
   *   The subscription plan code.
   */
  public function getPlanCode() {
    return $this->planCode;
  }

}
