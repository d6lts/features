<?php

/**
 * Implementation of hook_field_default_fields().
 */
function features_test_field_default_fields() {
  $fields = array();

  // Exported field: 'node-features_test-field_features_test'
  $fields['node-features_test-field_features_test'] = array(
    'field_config' => array(
      'translatable' => '1',
      'entity_types' => array(),
      'settings' => array(
        'max_length' => '255',
      ),
      'indexes' => array(
        'format' => array(
          '0' => 'format',
        ),
      ),
      'field_name' => 'field_features_test',
      'type' => 'text',
      'module' => 'text',
      'active' => '1',
      'cardinality' => '1',
      'deleted' => '0',
      'bundles' => array(
        'node' => array(
          '0' => 'features_test',
        ),
      ),
    ),
    'field_instance' => array(
      'label' => 'Test',
      'widget' => array(
        'weight' => '-4',
        'type' => 'text_textfield',
        'module' => 'text',
        'active' => 1,
        'settings' => array(
          'size' => '60',
        ),
      ),
      'settings' => array(
        'text_processing' => '0',
      ),
      'display' => array(
        'default' => array(
          'label' => 'above',
          'type' => 'text_default',
          'settings' => array(),
          'module' => 'text',
          'weight' => 0,
        ),
        'full' => array(
          'type' => 'hidden',
          'label' => 'above',
          'settings' => array(),
          'weight' => 0,
        ),
        'teaser' => array(
          'type' => 'hidden',
          'label' => 'above',
          'settings' => array(),
          'weight' => 0,
        ),
        'rss' => array(
          'type' => 'hidden',
          'label' => 'above',
          'settings' => array(),
          'weight' => 0,
        ),
      ),
      'required' => 0,
      'description' => '',
      'default_value' => NULL,
      'field_name' => 'field_features_test',
      'entity_type' => 'node',
      'bundle' => 'features_test',
      'deleted' => '0',
    ),
  );

  return $fields;
}
