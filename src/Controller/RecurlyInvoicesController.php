<?php

/**
 * @file
 * Contains \Drupal\recurly\Controller\RecurlyInvoicesController.
 */

namespace Drupal\recurly\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Returns responses for Recurly Subscription List.
 */
class RecurlyInvoicesController extends ControllerBase {

  /**
   * Retreive all invoices for the specified entity.
   *
   * @param EntityInterface $entity
   *   The entity associated with Recurly subscriptions; most typically a user.
   */
  public function invoicesList(EntityInterface $entity) {
    // Initialize the Recurly client with the site-wide settings.
    if (!recurly_client_initialize()) {
      return ['#markup' => $this->t('Could not initialize the Recurly client.')];
    }

    $entity_type = $entity->getEntityType()->getLowercaseLabel();
    $account = recurly_account_load(array('entity_type' => $entity_type, 'entity_id' => $entity->id()));

    $per_page = 20;
    $invoice_list = \Recurly_InvoiceList::getForAccount($account->account_code, array('per_page' => $per_page));
    $recurly_pager_manager = \Drupal::service('recurly.pager_manager');
    $invoices = $recurly_pager_manager->pagerResults($invoice_list, $per_page);

    return [
      '#theme' => 'recurly_invoice_list',
      '#attached' => [
        'library' => [
          'recurly/recurly.invoice',
        ],
      ],
      '#invoices' => $invoices,
      '#entity_type' => $entity_type,
      '#entity' => $entity,
      '#per_page' => $per_page,
      '#total' => $invoice_list->count(),
    ];
  }

  /**
   * Retrieve a single specified entity.
   *
   * @param EntityInterface $entity
   *   The entity associated with Recurly subscriptions; most typically a user.
   * @param string $invoice_number
   *   A Recurly invoice UUID.
   */
  public function getInvoice(EntityInterface $entity, $invoice_number) {
    // Initialize the Recurly client with the site-wide settings.
    if (!recurly_client_initialize()) {
      return ['#markup' => $this->t('Could not initialize the Recurly client.')];
    }

    $entity_type = $entity->getEntityType()->getLowercaseLabel();
    $account = recurly_account_load(['entity_type' => $entity_type, 'entity_id' => $entity->id()]);

    // Load the invoice.
    try {
      $invoice = \Recurly_Invoice::get($invoice_number);
    }
    catch (\Recurly_NotFoundError $e) {
      drupal_set_message($this->t('Invoice not found'));
      throw new NotFoundHttpException();
    }

    // Load the invoice account.
    $invoice_account = $invoice->account->get();

    // Ensure that the user account is the same as the invoice account.
    if (empty($account) || $invoice_account->account_code !== $account->account_code) {
      throw new NotFoundHttpException();
    }

    // @TODO
    // drupal_set_title() has been removed. There are now a few ways to set the title
    // dynamically, depending on the situation.
    //
    //
    // @see https://www.drupal.org/node/2067859
    // drupal_set_title(t('Invoice #@number', array('@number' => $invoice->invoice_number)));

    if ($invoice->state != 'collected') {
      $url = recurly_url('update_billing', ['entity' => $entity]);
      if ($url) {
        $error_message = $this->t('This invoice is past due! Please <a href="!url">update your billing information</a>.', ['!url' => $url->toString()]);
      }
      else {
        $error_message = $this->t('This invoice is past due! Please contact an administrator to update your billing information.');
      }
    }

    return [
      '#theme' => 'recurly_invoice',
      '#attached' => [
        'library' => [
          'recurly/recurly.invoice',
        ],
      ],
      '#invoice' => $invoice,
      '#invoice_account' => $invoice_account,
      '#entity_type' => $entity_type,
      '#entity' => $entity,
      '#error_message' => isset($error_message) ? $error_message : NULL,
    ];
  }

  /**
   * Deliver an invoice PDF file from Recurly.com.
   *
   * @param EntityInterface $entity
   *   The entity associated with Recurly subscriptions; most typically a user.
   * @param string $invoice_number
   *   A Recurly invoice UUID.
   */
  public function getInvoicePdf(EntityInterface $entity, $invoice_number) {
    // Initialize the Recurly client with the site-wide settings.
    if (!recurly_client_initialize()) {
      return ['#markup' => $this->t('Could not initialize the Recurly client.')];
    }

    $entity_type = $entity->getEntityType()->getLowercaseLabel();
    $account = recurly_account_load(['entity_type' => $entity_type, 'entity_id' => $entity->id()]);

    // Load the invoice.
    try {
      $invoice = \Recurly_Invoice::get($invoice_number);
      $pdf = \Recurly_Invoice::getInvoicePdf($invoice_number);
    }
    catch (\Recurly_NotFoundError $e) {
      drupal_set_message($this->t('Invoice not found'));
      throw new NotFoundHttpException();
    }

    // Load the invoice account.
    $invoice_account = $invoice->account->get();

    // Ensure that the user account is the same as the invoice account.
    if (empty($account) || $invoice_account->account_code !== $account->account_code) {
      throw new NotFoundHttpException();
    }

    if (!empty($pdf)) {
      if (headers_sent()) {
        die("Unable to stream pdf: headers already sent");
      }

      $response = new \Symfony\Component\HttpFoundation\Response();
      $response->headers->set('Content-Type', 'application/pdf', TRUE);
      $response->headers->set('Content-Disposition', 'inline; filename="' . $invoice_number . '.pdf"', TRUE);
      $response->sendHeaders();

      // I guess below is not necessary plus filesize was not working anyway?!
      // $response->headers->set('Content-Transfer-Encoding', 'binary', TRUE);
      // $response->headers->set('Content-Length', filesize($pdf), TRUE);
      print $pdf;
      drupal_exit();
    }
  }

}
