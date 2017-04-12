<?php

namespace Drupal\recurlyjs;

/**
 * Defines events for the configuration system.
 *
 * @see \Drupal\Core\Config\ConfigCrudEvent
 */
final class RecurlyJsEvents {

  /**
   * Name of the event fired before creating a new subscription.
   *
   * Allow other modules the chance to alter the new Recurly Subscription object
   * before it is saved. The event listener method receives a
   * \Recurly_Subscription instance.
   *
   * @Event
   *
   * @see \Drupal\recurlyjs\Form\submitForm()
   *
   * @var string
   */
  const SUBSCRIPTION_ALTER = 'recurlyjs.subscription.alter';

  /**
   * Name of the event fired after a subscription is created.
   *
   * Allow other modules to react to the new subscription being created. The
   * event listener method receives a \Recurly_Subscription instance.
   *
   * @Event
   *
   * @see \Drupal\recurlyjs\Form\submitForm()
   *
   * @var string
   */
  const SUBSCRIPTION_CREATED = 'recurlyjs.subscription.created';

}
