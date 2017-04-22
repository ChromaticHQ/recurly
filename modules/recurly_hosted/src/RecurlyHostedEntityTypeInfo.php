<?php

namespace Drupal\recurly_hosted;

use Drupal\recurly\RecurlyEntityTypeInfo;

/**
 * Service class for manipulating entity type information.
 *
 * This class contains primarily bridged hooks for compile-time or
 * cache-clear-time hooks. Runtime hooks should be placed in EntityOperations.
 */
class RecurlyHostedEntityTypeInfo extends RecurlyEntityTypeInfo {

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
    // Only act if the Recurly entity type definition is found.
    if (!$entity_type = $this->recurlyEntityTypeDefinition($entity_types)) {
      return;
    }
    // Set link template paths for the Recurly entity.
    $entity_type->setLinkTemplate('recurly-updatebilling', $entity_type->getLinkTemplate('canonical') . '/subscription/billing');
  }

}
