<?php

/**
 * @file
 * Contains \Drupal\recurly\RecurlyEntityOperations.
 */

namespace Drupal\recurly;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Defines a class for reacting to entity events.
 */
class RecurlyEntityOperations {

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new RecurlyEntityOperations object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Acts on an entity being updated.
   *
   * Update the Recurly remote account when the local Drupal entity is updated.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity being updated.
   */
  public function entityUpdate(EntityInterface $entity) {
    $entity_type = $entity->getEntityTypeId();
    // If this isn't the enabled Recurly entity type, do nothing.
    if (\Drupal::config('recurly.settings')->get('recurly_entity_type') !== $entity_type) {
      return;
    }

    // Check if this entity has a remote Recurly account that we should sync.
    $local_account = recurly_account_load(['entity_type' => $entity_type, 'entity_id' => $entity->id()], TRUE);
    if (!$local_account) {
      return;
    }

    // Check if any of the mapping tokens have changed.
    if (!$original_entity = \Drupal::entityManager()->getStorage($entity_type)->load($entity->getOriginalId())) {
      return;
    }
    $original_values = [];
    $updated_values = [];

    $recurly_token_manager = \Drupal::service('recurly.token_manager');
    foreach ($recurly_token_manager->tokenMapping() as $recurly_field => $token) {
      $original_values[$recurly_field] = \Drupal::token()->replace($token, [$entity_type => $original_entity], ['clear' => TRUE, 'sanitize' => FALSE]);
      $updated_values[$recurly_field] = \Drupal::token()->replace($token, [$entity_type => $entity], ['clear' => TRUE, 'sanitize' => FALSE]);
    }
    $original_values['username'] = $original_entity->label();
    $updated_values['username'] = $entity->label();

    // If there are any changes, push them to Recurly.
    if ($original_values !== $updated_values) {
      try {
        $recurly_account = recurly_account_load(['entity_type' => $entity_type, 'entity_id' => $entity->id()]);
        $address_fields = [
          'address1',
          'address2',
          'city',
          'state',
          'zip',
          'country',
          'phone',
        ];
        foreach ($updated_values as $field => $value) {
          if (strlen($value)) {
            if (in_array($field, $address_fields)) {
              // The Recurly PHP client doesn't check for nested objects when
              // determining what properties have changed when updating an
              // object. This works around it by re-assigning the address
              // property instead of directly modifying the address's fields.
              // This can be removed when
              // https://github.com/recurly/recurly-client-php/pull/80 is merged
              // in.
              //
              // $recurly_account->address->{$field} = $value;
              $adr = $recurly_account->address;
              $adr->{$field} = $value;
              $recurly_account->address = $adr;
            }
            else {
              $recurly_account->{$field} = $value;
            }
          }
        }
        $recurly_account->update();
      }
      catch (Recurly_Error $e) {
        drupal_set_message(t('The billing system reported an error: "@error" To ensure proper billing, please correct the problem if possible or contact support.', ['@error' => $e->getMessage()]), 'warning');
        \Drupal::logger('recurly')->error('Account information could not be sent to the Recurly, it reported "@error" while trying to update !title with the values @values.', [
          '@error' => $e->getMessage(),
          '!title' => \Drupal::l($entity->label(), $entity->toUrl()),
          '@values' => print_r($updated_values, 1),
        ]);
      }
    }
  }

  /**
   * Acts on an entity being deleted.
   *
   * This hook is *not* called when a user cancels their account through any
   * mechanism other than "delete account". This fires when user accounts are
   * being deleted, or when subscriptions are on other entities, such as nodes.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity being updated.
   */
  public function entityDelete(EntityInterface $entity) {
    if (($entity_type = $entity->getEntityTypeId()) == \Drupal::config('recurly.settings')->get('recurly_entity_type')) {
      // Check for a local account first, no need to attempt to close an account
      // if we don't have any information about it.
      $local_account = recurly_account_load(['entity_type' => $entity_type, 'entity_id' => $entity->id()], TRUE);
      if ($local_account) {
        $recurly_account = recurly_account_load(['entity_type' => $entity_type, 'entity_id' => $entity->id()]);
        recurly_account_delete($recurly_account);
      }
    }
  }

}
