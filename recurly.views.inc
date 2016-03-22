<?php

/**
 * @file
 * Views hooks for Recurly module.
 */

/**
 * Implements hook_views_data().
 */
function recurly_views_data() {
  $data = [];
  $data['recurly_account'] = [
    'table' => [
      'group' => t('Recurly account'),
      'base' => [
        'field' => 'account_code',
        'title' => t('Recurly account'),
        'help' => t('Contains Recurly accounts associated with entities.'),
      ],
    ],
  ];

  $recurly_account = &$data['recurly_account'];

  $recurly_account['account_code'] = [
    'title' => t('Account code'),
    'help' => t('The unique Recurly account code.'),
    'field' => [
      'id' => 'recurly_account_code',
      'click sortable' => TRUE,
    ],
    'sort' => [
      'id' => 'standard',
    ],
    'filter' => [
      'id' => 'string',
    ],
    'argument' => [
      'id' => 'string',
    ],
  ];

  $recurly_account['status'] = [
    'title' => t('Account status'),
    'help' => t('The Recurly account status.'),
    'field' => [
      'id' => 'standard',
      'click sortable' => TRUE,
    ],
    'sort' => [
      'id' => 'standard',
    ],
    'filter' => [
      'id' => 'string',
    ],
    'argument' => [
      'id' => 'string',
    ],
  ];

  $recurly_account['updated'] = [
    'title' => t('Updated'),
    'help' => t('The time the account was last updated.'),
    'field' => [
      'id' => 'date',
      'click sortable' => TRUE,
    ],
    'sort' => [
      'handler' => 'date',
    ],
    'filter' => [
      'handler' => 'date',
    ],
  ];

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
    $description = t('@base-entity from @join-entity', ['@join-entity' => $info->getLabel()->render(), '@base-entity' => 'Recurly Account']);
    if ($base_table) {
      $data[$base_table]['recurly_' . $entity_type] = [
        'title' => t('Recurly Account'),
        'help' => t('The Recurly Account of this @entity-type.', ['@entity-type' => $entity_type]),
        'relationship' => [
          'base' => 'recurly_account',
          'base field' => 'entity_id',
          'field' => $info->getKeys()['id'],
          'id' => 'recurly_entity_owner_reverse',
          'label' => $description,
        ],
      ];
    }
  }
}

/**
 * Return the relationship data to join to an arbitrary entity type.
 *
 * @param string $label
 *   The label to use for the base table or entity type, such as
 *   "Recurly account".
 * @param string $join_entity_type
 *   The entity type being joined to, such as 'user'.
 *
 * @return array
 *   An array suitable for using with a 'join' key in a table definition.
 */
function _recurly_entity_relationship_data($label, $join_entity_type) {
  $join_entity_info = \Drupal::entityTypeManager()->getDefinition($join_entity_type);
  $description = t('@join-entity from @base-entity', ['@join-entity' => $join_entity_info->getLabel()->render(), '@base-entity' => $label]);

  // 'entity type' is a key not from Views directly, but used to determine
  // what entity type need to filter on in our relationship handler.
  return [
    'title' => $join_entity_info->getLabel()->render(),
    'help' => t('The @entity-type owner of this @label.', ['@entity-type' => $join_entity_type, '@label' => $label]),
    'relationship' => [
      'entity type' => $join_entity_type,
      'base' => $join_entity_info->getBaseTable(),
      'base field' => $join_entity_info->getKeys()['id'],
      'field' => 'entity_id',
      'id' => 'recurly_entity_owner',
      'label' => $description,
      'title' => $description,
    ],
  ];
}
