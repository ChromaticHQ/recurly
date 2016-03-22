<?php

/**
 * @file
 * Contains Drupal\recurly\RecurlyEntityTypeInfo.
 */

namespace Drupal\recurly;

/**
 * Service class for manipulating entity type information.
 *
 * This class contains primarily bridged hooks for compile-time or
 * cache-clear-time hooks. Runtime hooks should be placed in EntityOperations.
 */
class RecurlyEntityTypeInfo {

  /**
   * Adds Recurly subscription configuration to appropriate entity types.
   *
   * This is an alter hook bridge.
   *
   * @param EntityTypeInterface[] $entity_types
   *   The master entity type list to alter.
   *
   * @see hook_entity_type_alter()
   */
  public function entityTypeAlter(array &$entity_types) {
    $recurly_entity_type = \Drupal::config('recurly.settings')->get('recurly_entity_type') ?: 'user';
    $entity_type = $entity_types[$recurly_entity_type];
    // Set up our link templates to be used in our routes.
    // See alterRoutes in Drupal\recurly\Routing\RecurlyRouteSubscriber.
    if ($entity_type->hasViewBuilderClass() && $entity_type->hasLinkTemplate('canonical')) {
      $entity_type->setLinkTemplate('recurly-subscriptionlist', $entity_type->getLinkTemplate('canonical') . '/subscription');
      $entity_type->setLinkTemplate('recurly-signup', $entity_type->getLinkTemplate('canonical') . '/subscription/signup');
      $entity_type->setLinkTemplate('recurly-change', $entity_type->getLinkTemplate('canonical') . '/subscription/change');
      $entity_type->setLinkTemplate('recurly-planchange', $entity_type->getLinkTemplate('canonical') . '/subscription/id/{subscription_id}/change/{new_plan_code}');
      $entity_type->setLinkTemplate('recurly-cancellatest', $entity_type->getLinkTemplate('canonical') . '/subscription/cancel');
      $entity_type->setLinkTemplate('recurly-cancel', $entity_type->getLinkTemplate('canonical') . '/subscription/id/{subscription_id}/cancel');
      $entity_type->setLinkTemplate('recurly-reactivatelatest', $entity_type->getLinkTemplate('canonical') . '/subscription/reactivate');
      $entity_type->setLinkTemplate('recurly-reactivate', $entity_type->getLinkTemplate('canonical') . '/subscription/id/{subscription_id}/cancel');
      $entity_type->setLinkTemplate('recurly-invoices', $entity_type->getLinkTemplate('canonical') . '/subscription/invoices');
      $entity_type->setLinkTemplate('recurly-invoice', $entity_type->getLinkTemplate('canonical') . '/subscription/invoices/{invoice_number}');
      $entity_type->setLinkTemplate('recurly-invoicepdf', $entity_type->getLinkTemplate('canonical') . '/subscription/invoices/{invoice_number}/pdf');
      $entity_type->setLinkTemplate('recurly-coupon', $entity_type->getLinkTemplate('canonical') . '/subscription/redeem-coupon');
    }
  }

}
