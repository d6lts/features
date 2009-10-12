<?php
// $Id$
?>
<div class='features-form clear-block'>
  <?php if (count($packages) > 1): ?>
    <div class='features-form-content'>
      <?php foreach ($packages as $class => $package): ?>
        <div class='features-form-package package-<?php print $class?>'><?php print drupal_render($package) ?></div>
      <?php endforeach; ?>
      <?php print drupal_render($form) ?>
    </div>
    <div class='features-form-links'><?php print theme('links', $package_links, array('id' => 'features-form-links')) ?></div>
  <?php else: ?>
    <?php print drupal_render($packages) ?>
    <?php print drupal_render($form) ?>
  <?php endif; ?>
</div>
