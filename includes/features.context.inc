<?php
// $Id$

/**
 * Implementation of hook_features_export().
 */
function context_features_export($data, &$export, $module_name = '') {
  $export['dependencies']['context'] = 'context';

  // Collect a context to module map
  $map = array();
  $modules = module_implements('context_default_contexts');
  foreach ($modules as $module) {
    $contexts = module_invoke($module, 'context_default_contexts');
    foreach ($contexts as $context) {
      $map["{$context['namespace']}-{$context['attribute']}-{$context['value']}"] = $module;
    }
  }

  $pipe = array();
  $contexts = context_enabled_contexts();
  foreach ($data as $identifier) {
    // If this context is already provided by another module, add it
    // as a dependency and prevent it from becoming a duplicate export.
    if (isset($map[$identifier]) && $map[$identifier] != $module_name) {
      if (isset($export['features']['context'][$identifier])) {
        unset($export['features']['context'][$identifier]);
      }
      $module = $map[$identifier];
      $export['dependencies'][$module] = $module;
    }
    // Otherwise, export it.
    else if (!empty($contexts[$identifier])) {
      $export['features']['context'][$identifier] = $identifier;

      $context = $contexts[$identifier];

      foreach (array('node', 'menu') as $key) {
        if (!empty($context->{$key})) {
          if (is_array($context->{$key})) {
            foreach ($context->{$key} as $item) {
              $pipe[$key][$item] = $item;
            }
          }
          else {
            $item = $context->{$key};
            $pipe[$key][$item] = $item;
          }
        }
      }

      // Special pipe for views
      if (!empty($context->views) && is_array($context->views)) {
        foreach ($context->views as $view_name) {
          $split = explode(':', $view_name);
          $view_name = array_shift($split);
          $pipe['views'][$view_name] = $view_name;
        }
      }

      // Special pipe for blocks
      if (!empty($context->block)) {
        foreach ($context->block as $block) {
          $block = (array) $block;
          $bid = "{$block['module']}-{$block['delta']}";
          $pipe['block'][$bid] = $bid;
        }
      }
    }
  }
  return $pipe;
}

/**
 * Implementation of hook_features_export_render()
 */
function context_features_export_render($module = 'foo', $data) {
  $code = array();
  $code[] = '  $items = array();';
  $code[] = '';

  foreach ($data as $identifier) {
    $contexts = context_enabled_contexts();
    $context = $contexts[$identifier];

    // prune system specific information and cast for Drupal's AOP (array oriented programming)
    $prune = array('cid', 'status', 'system', 'type');
    foreach ($prune as $key) {
      if (isset($context->{$key})) {
        unset($context->{$key});
      }
    }
    $context = (array) $context;

    // clean up blocks
    if (!empty($context['block'])) {
      foreach ($context['block'] as $bid => $block) {
        unset($block->bid);
        $context['block'][$bid] = (array) $block;
      }
    }

    $code[] = '  $items[] = '. context_var_export($context, '  ') .';';
  }

  $code[] = '  return $items;';
  $code = implode("\n", $code);
  return array('context_default_contexts' => $code);
}
