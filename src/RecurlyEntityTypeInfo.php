<?php

namespace Drupal\recurly;

use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Service class for manipulating entity type information.
 *
 * This class contains primarily bridged hooks for compile-time or
 * cache-clear-time hooks. Runtime hooks should be placed in EntityOperations.
 */
class RecurlyEntityTypeInfo {

  /**
   * The Recurly settings.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $recurlySettings;

  /**
   * Constructs the Recurly format manager.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config service.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    $this->recurlySettings = $config_factory->get('recurly.settings');
  }

  /**
   * Adds Recurly subscription configuration to appropriate entity types.
   *
   * This is an alter hook bridge.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface[] $entity_types
   *   The master entity type list to alter.
   *
   * @see hook_entity_type_alter()
   */
  public function entityTypeAlter(array &$entity_types) {
    // Only act if the Recurly entity type definition is found.
    if (!$entity_type = $this->recurlyEntityTypeDefinition($entity_types)) {
      return;
    }
    // Get the canonical path to the Recurly entity.
    $canonical_path = $entity_type->getLinkTemplate('canonical');
    // Set link template paths for the Recurly entity.
    $entity_type->setLinkTemplate('recurly-subscriptionlist', $canonical_path . '/subscription');
    $entity_type->setLinkTemplate('recurly-signup', $canonical_path . '/subscription/signup');
    $entity_type->setLinkTemplate('recurly-change', $canonical_path . '/subscription/change');
    $entity_type->setLinkTemplate('recurly-planchange', $canonical_path . '/subscription/id/{subscription_id}/change/{new_plan_code}');
    $entity_type->setLinkTemplate('recurly-cancellatest', $canonical_path . '/subscription/cancel');
    $entity_type->setLinkTemplate('recurly-cancel', $canonical_path . '/subscription/id/{subscription_id}/cancel');
    $entity_type->setLinkTemplate('recurly-reactivatelatest', $canonical_path . '/subscription/reactivate');
    $entity_type->setLinkTemplate('recurly-reactivate', $canonical_path . '/subscription/id/{subscription_id}/reactivate');
    $entity_type->setLinkTemplate('recurly-invoices', $canonical_path . '/subscription/invoices');
    $entity_type->setLinkTemplate('recurly-invoice', $canonical_path . '/subscription/invoices/{invoice_number}');
    $entity_type->setLinkTemplate('recurly-invoicepdf', $canonical_path . '/subscription/invoices/{invoice_number}/pdf');
    $entity_type->setLinkTemplate('recurly-coupon', $canonical_path . '/subscription/redeem-coupon');
  }

  /**
   * Gets the Recurly entity type definition.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface[] $entity_types
   *   An array of entity type definitions.
   *
   * @return \Drupal\Core\Entity\EntityTypeInterface
   *   The entity definition for the Recurly entity type.
   */
  protected function recurlyEntityTypeDefinition(array $entity_types) {
    // Get the entity type we're using, or bail if it hasn't been set yet.
    if (!$recurly_entity_type = $this->recurlySettings->get('recurly_entity_type')) {
      return FALSE;
    }
    // Set up our link templates to be used in our routes.
    // See alterRoutes in Drupal\recurly\Routing\RecurlyRouteSubscriber.
    $entity_type = $entity_types[$recurly_entity_type];
    if ($entity_type->hasViewBuilderClass() && $entity_type->hasLinkTemplate('canonical')) {
      return $entity_type;
    }

    return FALSE;
  }

}
