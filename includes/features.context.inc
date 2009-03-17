<?php
// $Id$

/**
 * Implementation of hook_features_export().
 */
function context_features_export($data, &$export) {
  $export['dependencies']['context'] = 'context';

  $pipe = array();
  $contexts = context_enabled_contexts();
  foreach ($data as $identifier) {
    if (!empty($contexts[$identifier])) {
      $export['features']['context'][$identifier] = $identifier;

      $context = $contexts[$identifier];
      if (!empty($context->node)) {
        $pipe['node'] = $context->node;
      }
      if (!empty($context->views)) {
        $pipe['views'] = $context->views;
      }
      if (!empty($context->menu)) {
        $pipe['menu'] = array($context->menu);
      }
      // Special pipe for blocks
      if (!empty($context->block)) {
        $pipe['block'] = array();
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
  $code[] = '/**';
  $code[] = ' * Implementation of hook_context_default_contexts().';
  $code[] = ' */';
  $code[] = "function {$module}_context_default_contexts() {";
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
  $code[] = '}';
  return implode("\n", $code);
}
