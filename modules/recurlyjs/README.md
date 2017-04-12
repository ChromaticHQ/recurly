# RecurlyJS
## Description
This module provides FormAPI elements for subscriptions, updating billing
information, and one-time payments through Recurly.com.

IMPORTANT CAVEAT: Recurlyjs does not use Drupal to process sensitive
information, so it never touches your server (thus circumventing PCI compliance
requirements). This comes with the following caveats:

* Users must have JavaScript enabled in their browser to submit payment.
* Recurly.js is compatible with IE7 and higher.
* Recurly.js recommends jQuery 1.5.2 or higher. Drupal 7 comes with jQuery 1.4.4
  out of the box, however we are not aware of any issues with using Recurly.js
  with this older version of jQuery.

## Requirements
* Drupal 8.x
* Recurly

The recurlyjs library is dynamically included does not need to be downloaded.

The README.md for the Recurly module describes the installation and
configuration for this module.

## Extending

This module provides an event that allows a subscription to be altered and an
event that allows other modules to respond to the creation of a subscription.
The events are further documented in `\Drupal\recurlyjs\RecurlyJsEvents` and
`\Drupal\recurlyjs\Event\Subscription`. The following is sample code for
integrating with these events.

`example.services.yml`

```
  example.subscription_event_subscriber:
    class: Drupal\example\Event\ExampleEventSubscriber
    tags:
      - { name: event_subscriber }
```

`\Drupal\example\Event\ExampleEventSubscriber`

```
namespace Drupal\example\Event;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\recurlyjs\RecurlyJsEvents;
use Drupal\recurlyjs\Event\SubscriptionAlter;
use Drupal\recurlyjs\Event\SubscriptionCreated;

/**
 * Alter subscriptions.
 */
class ExampleEventSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[RecurlyJsEvents::SUBSCRIPTION_CREATED][] = ['subscriptionCreated'];
    $events[RecurlyJsEvents::SUBSCRIPTION_ALTER][] = ['subscriptionAlter'];
    return $events;
  }

  /**
   * Alter a subscription before it is created.
   */
  public function subscriptionAlter(SubscriptionAlter $event) {
    $subscription = $event->getSubscription();
    $subscription->plan_code = 12345;
    $event->updateSubscription($subscription);
  }

  /**
   * Respond to a subscription being created.
   */
  public function subscriptionCreated(SubscriptionCreated $event) {
    $subscription = $event->getSubscription();
    // Do something based upon the subscription being created.
  }

}
```

## Support
Please use the issue queue for filing bugs with this module at
http://drupal.org/project/issues/recurly?categories=All

WE DO NOT PROVIDE SUPPORT ON CUSTOM CODING. Please use the issue queue for
issues that are believed to be bugs or for requesting features.
