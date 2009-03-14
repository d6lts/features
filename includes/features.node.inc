<?php
// $Id$

/**
 * Implementation of hook_features_export_options().
 */
function node_features_export_options() {
  return node_get_types('names');
}

/**
 * Implementation of hook_features_export.
 */
function node_features_export($data, &$export, $module_name = '') {
  $pipe = array();

  // Collect a real node type to module map
  $type_map = array();
  $modules = module_implements('node_info');
  foreach ($modules as $module) {
    $callback = $module ."_node_info";
    foreach (call_user_func($callback) as $module_type => $module_info) {
      $type_map[$module_type] = $module;
    }
  }

  foreach ($data as $type) {
    // Poll node module to determine who provides the node type.
    if ($info = node_get_types('module', $type)) {

      $export['dependencies'][$info] = $info;
      if ($info == 'node') {
        $export['items']['node'][$type] = $type;
      }

      // If this node type is provided by a different module, add it as a dependency
      $module = $type_map[$type];
      if (!$module && $module != $module_name) {
        $export['dependencies'][$module] = $module;
      }

      // Create a pipe for CCK fields
      if (module_exists('content')) {
        $content_info = content_types($type);
        if (!empty($content_info['fields'])) {
          foreach ($content_info['fields'] as $key => $field) {
            $pipe['content'][] = "{$type}-{$field['field_name']}";
          }
        }
      }

    }
  }

  return $pipe;
}

/**
 * Implementation of hook_features_export_render().
 */
function node_features_export_render($module = 'foo', $data) {
  $elements = array(
    'name' => TRUE,
    'module' => FALSE,
    'description' => TRUE,
    'has_title' => FALSE,
    'title_label' => TRUE,
    'has_body' => FALSE,
    'body_label' => TRUE,
  );
  $output = '';

  foreach ($data as $type) {
    $info = node_get_types('type', $type);
    $definition = '';
    foreach ($elements as $key => $t) {
      if ($t) {
        $definition .= "        \"{$key}\" => t(\"" . str_replace('"', '\"', $info->$key). "\"),\n";
      }
      else {
        $definition .= "        \"{$key}\" => \"{$info->$key}\",\n";
      }
    }
    $output .= <<<EOT

      "$type" => array(
$definition      ),
EOT;
  }
  return <<<EOT
/**
 * Implementation of hook_node_info().
 */
function {$module}_node_info() {
  return array($output
  );
}
EOT;
}

