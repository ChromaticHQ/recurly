<?php

/**
 * @file
 * Contains \Drupal\recurly\Plugin\views\relationship\EntityOwner.
 */

namespace Drupal\recurly\Plugin\views\relationship;

use Drupal\views\Views;
use Drupal\views\Plugin\views\relationship\RelationshipPluginBase;
use Drupal\views\Plugin\views\join\JoinPluginBase;

/**
 * Views Relationship handler to allow joins to an arbitrary entity.
 *
 * The Recurly module allows accounts to be associated with any entity type,
 * and not just users. This means we can't just use Entity API or Views on the
 * entity_id column directly, since it won't know what entity type the ID
 * corresponds to.
 *
 * To use this handler, the Views table definition must contain an
 * 'entity type' key specifying the entity type for the specific handler.
 *
 * @ViewsRelationship("entity_owner")
 */
class EntityOwner extends RelationshipPluginBase {

  /**
   * {@inheritdoc}
   */
  public function query() {
    $this->ensureMyTable();

    // Figure out what base table this relationship brings to the party.
    // dpm($this->options, 'options');
    // return parent::query();
    $table_data = Views::viewsData()->get($this->definition['base']);
    $base_field = empty($this->definition['base field']) ? $table_data['table']['base']['field'] : $this->definition['base field'];

    $def = $this->definition;
    $def['table'] = $this->definition['base'];
    $def['field'] = $base_field;
    $def['left_table'] = $this->tableAlias;
    $def['left_field'] = 'entity_id';
    $def['adjusted'] = TRUE;
    $def['operator'] = '=';
    if (!empty($this->options['required'])) {
      $def['type'] = 'INNER';
    }

    $def['extra'] = sprintf("%s.entity_type = '%s'", $def['left_table'], $def['entity type']);

    // Is this location still valid?
    if (!empty($def['join_handler']) && class_exists($def['join_handler'])) {
      $join = new $def['join_handler']();
    }
    else {
      $join = Views::pluginManager('join')->createInstance('standard', $def);
    }

    // Use a short alias for this.
    $alias = $def['table'] . '_' . $this->table;
    $this->alias = $this->query->addRelationship($alias, $join, $this->definition['base'], $this->relationship);

    // Add access tags if the base table provide it.
    if (empty($this->query->options['disable_sql_rewrite']) && isset($table_data['table']['base']['access query tag'])) {
      $access_tag = $table_data['table']['base']['access query tag'];
      $this->query->addTag($access_tag);
    }
  }
}
