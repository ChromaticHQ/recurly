<?php
/**
 * @file
 * Output a summary of a subscription with links to manage it.
 */
?>
<div class="subscription mini clearfix">
  <div class="subscription-summary clearfix">
    <h2><?php print $plan_name; ?></h2>
    <?php if (!empty($message)) : ?>
      <div class="messages warning"><h2 class="element-invisible"><?php print('Warning message'); ?></h2><?php print $message; ?></div>
    <?php endif; ?>
    <table class="properties">
      <tr class="status">
        <th><?php print t('Status'); ?></th>
        <td><?php print recurly_format_state($state); ?></td>
      </tr>
      <tr>
        <th>Start Date</th>
        <td><?php print $start_date; ?></td>
      </tr>
      <tr>
        <th>Next Invoice</th>
        <td><?php print $current_period_ends_at; ?></td>
      </tr>
    </table>
    <div class="line-items">
      <ul>
        <li>
          <div class="qty"><?php print $quantity; ?></div>
          <div class="cost"><?php print $cost; ?></div>
          <div class="name"><?php print $plan_name; ?></div>
        </li>
        <?php foreach ($add_ons as $add_on): ?>
        <li>
          <div class="qty"><?php print $add_on['quantity']; ?></div>
          <div class="cost"><?php print $add_on['cost']; ?></div>
          <div class="name"><?php print $add_on['name']; ?></div>
        </li>
        <?php endforeach; ?>
      </ul>
      <div class="total"><?php print $total; ?></div>
    </div>
  </div>
  <div class="subscription-links clearfix">
    <?php print $subscription_links; ?>
  </div>
</div>
