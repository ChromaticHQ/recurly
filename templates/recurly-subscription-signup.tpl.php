<?php
/**
 * @file
 * Display a signup page listing all the available plans.
 */
?>
<div class="recurly-signup">
  <?php foreach ($filtered_plans as $plan): ?>
    <div class="plan plan-<?php print $plan['plan_code']; ?>">
      <h2><?php print $plan['name']; ?></h2>
      <div class="plan-interval"><?php print $plan['plan_interval']; ?></div>
      <?php if ($plan['trial_interval']): ?>
        <div class="plan-trial"><?php print $plan['trial_interval']; ?></div>
      <?php endif; ?>
      <div class="plan-signup">
        <?php if ($plan['signup_url']): ?>
        <a href="<?php print $plan['signup_url']; ?>"><?php print t('Sign up'); ?></a>
        <?php else: ?>
        <?php print t('Contact us to sign up'); ?>
        <?php endif; ?>
      </div>
      <div class="plan-description"><?php print nl2br($plan['description']); ?></div>
    </div>
  <?php endforeach; ?>
</div>
