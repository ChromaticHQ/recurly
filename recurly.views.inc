<?php

/**
 * @file
 * Views hooks for Recurly module.
 */

/**
 * Implements hook_views_data().
 */
function recurly_views_data() {
  $data = array();
  $data['recurly_account'] = array(
    'table' => array(
      'group' => t('Recurly account'),
      'base' => array(
        'field' => 'account_code',
        'title' => t('Recurly account'),
        'help' => t('Contains Recurly accounts associated with entities.'),
      ),
    ),
  );

  $recurly_account = &$data['recurly_account'];

  $recurly_account['account_code'] = array(
    'title' => t('Account code'),
    'help' => t('The unique Recurly account code.'),
    'field' => array(
      'id' => 'recurly_account_code',
      'click sortable' => TRUE,
    ),
    'sort' => array(
      'id' => 'standard',
    ),
    'filter' => array(
      'id' => 'string',
    ),
    'argument' => array(
      'id' => 'string',
    ),
  );

  $recurly_account['status'] = array(
    'title' => t('Account status'),
    'help' => t('The Recurly account status.'),
    'field' => array(
      'id' => 'standard',
      'click sortable' => TRUE,
    ),
    'sort' => array(
      'id' => 'standard',
    ),
    'filter' => array(
      'id' => 'string',
    ),
    'argument' => array(
      'id' => 'string',
    ),
  );

  $recurly_account['updated'] = array(
    'title' => t('Updated'),
    'help' => t('The time the account was last updated.'),
    'field' => array(
      'id' => 'date',
      'click sortable' => TRUE,
    ),
    'sort' => array(
      'handler' => 'date',
    ),
    'filter' => array(
      'handler' => 'date',
    ),
  );

  // Join from recurly_account to all entity types, using the entity_type and
  // entity_id columns.
  $entity_info = \Drupal::entityManager()->getDefinitions();

  foreach (array_keys($entity_info) as $entity_type) {
    $recurly_account[$entity_type] = _recurly_entity_relationship_data('Recurly account', $entity_type);
  }

  return $data;
}

/**
 * Implements hook_views_data_alter().
 */
function recurly_views_data_alter(&$data) {
  // Add relationships from entity types to Recurly Accounts.
  $entity_info = \Drupal::entityTypeManager()->getDefinitions();
  foreach ($entity_info as $entity_type => $info) {
    $base_table = $info->getBaseTable();
    $description = t('@base-entity from @join-entity', array('@join-entity' => $info->getLabel()->render(), '@base-entity' => 'Recurly Account'));
    if ($base_table) {
      $data[$base_table][$entity_type] = array(
        'title' => t('Recurly Account'),
        'help' => t('The Recurly Account of this @entity-type.', array('@entity-type' => $entity_type)),
        'relationship' => array(
          'base' => 'recurly_account',
          'base field' => 'entity_id',
          'field' => $info->getKeys()['id'],
          'id' => 'recurly_entity_owner_reverse',
          'label' => $description,
        ),
      );
    }
  }
}

/**
 * Return the relationship data to join to an arbitrary entity type.
 *
 * @param string $label
 *   The label to use for the base table or entity type, such as
 *   "Recurly account."
 * @param string $join_entity_type
 *   The entity type being joined to, such as 'user'.
 *
 * @return array
 *   An array suitable for using with a 'join' key in a table definition.
 */
function _recurly_entity_relationship_data($label, $join_entity_type) {
  $join_entity_info = \Drupal::entityTypeManager()->getDefinition($join_entity_type);
  $description = t('@join-entity from @base-entity', array('@join-entity' => $join_entity_info->getLabel()->render(), '@base-entity' => $label));

  // 'entity type' is a key not from Views directly, but used to determine
  // what entity type need to filter on in our relationship handler.
  return array(
    'title' => $join_entity_info->getLabel()->render(),
    'help' => t('The @entity-type owner of this @label.', array('@entity-type' => $join_entity_type, '@label' => $label)),
    'relationship' => array(
      'entity type' => $join_entity_type,
      'base' => $join_entity_info->getBaseTable(),
      'base field' => $join_entity_info->getKeys()['id'],
      'field' => 'entity_id',
      'id' => 'recurly_entity_owner',
      'label' => $description,
      'title' => $description,
    ),
  );
}
