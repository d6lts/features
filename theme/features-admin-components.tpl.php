<?php
// $Id$
?>
<div class='clear-block features-components'>
  <div class='column'>
    <div class='info'>
      <h3><?php print $name ?></h3>
      <div class='description'><?php print $description ?></div>
      <?php print $dependencies ?>
    </div>
  </div>
  <div class='column'>
    <div class='components'>
      <?php print $components ?>
      <div class='buttons clear-block'><?php print $buttons ?></div>
    </div>
  </div>
  <?php print drupal_render_children($form) ?>
</div>
