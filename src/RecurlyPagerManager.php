<?php

/**
 * @file
 * Contains \Drupal\recurly\RecurlyPagerManager.
 */

namespace Drupal\recurly;

/**
 * Recurly pager utility functionality.
 */
class RecurlyPagerManager {

  /**
   * Utility function to retrieve a specific page of results from Recurly_Pager.
   *
   * @param object $pager_object
   *   Any object that extends a Recurly_Pager object, such as a
   *   Recurly_InvoiceList, Recurly_SubscriptionList, or
   *   Recurly_TransactionList.
   * @param int $per_page
   *   The number of items to display per page.
   * @param int $page_num
   *   The desired page number to display. Usually automatically determined from
   *   the URL.
   */
  public function pagerResults($pager_object, $per_page, $page_num = NULL) {
    if (!isset($page_num)) {
      $page_num = isset($_GET['page']) ? (int) $_GET['page'] : 0;
    }

    // Fast forward the list to the current page.
    $start = $page_num * $per_page;
    for ($n = 0; $n < $start; $n++) {
      $pager_object->next();
    }

    // Populate $page_results with the current page.
    $total = $pager_object->count();
    $page_end = min($start + $per_page, $total);
    $page_results = array();
    for ($n = $start; $n < $page_end; $n++) {
      $item = $pager_object->current();
      $page_results[$item->uuid] = $item;
      $pager_object->next();
    }

    pager_default_initialize($total, $per_page);

    return $page_results;
  }

}
