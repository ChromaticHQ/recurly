<?php
// @FIXME
// The Assets API has totally changed. CSS, JavaScript, and libraries are now
// attached directly to render arrays using the #attached property.
// 
// 
// @see https://www.drupal.org/node/2169605
// @see https://www.drupal.org/node/2408597
// /**
//  * @file
//  * Print out the subscription page for a particular plan.
//  */
// drupal_add_css(drupal_get_path('module', 'recurlyjs') . '/css/recurlyjs.css');

?>
<div id="subscribe-page">
  <?php print \Drupal::service("renderer")->render($form); ?>
</div>
