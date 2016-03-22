<?php

/**
 * @file
 * Contains \Drupal\recurly\Plugin\views\field\AccountCode.
 */

namespace Drupal\recurly\Plugin\views\field;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\ResultRow;
use Drupal\views\Plugin\views\field\FieldPluginBase;

/**
 * Add a link to view a Recurly account.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("recurly_account_code")
 */
class AccountCode extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['link_to_recurly'] = ['default' => TRUE];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    $form['link_to_recurly'] = [
      '#type' => 'checkbox',
      '#title' => t('Link this field to the account at Recurly'),
      '#description' => $this->t("Enable to link this field to the account in Recurly's user interface"),
      '#default_value' => $this->options['link_to_recurly'],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $value = $this->getValue($values);
    return $this->renderLink($this->sanitizeValue($value), $values);
  }

  /**
   * Render a link for a Recurly account.
   *
   * @param string $data
   *   Sanitized data to render.
   * @param ResultRow $values
   *   An ResultRow object of values associated with this row.
   *
   * @return mixed
   *   A rendered account string.
   */
  private function renderLink($data, ResultRow $values) {
    if (!empty($this->options['link_to_recurly']) && ($account_code = $this->getValue($values)) && $data !== NULL && $data !== '') {
      $this->options['alter']['make_link'] = TRUE;
      $recurly_url_manager = \Drupal::service('recurly.url_manager');
      $this->options['alter']['path'] = $recurly_url_manager->hostedUrl('accounts/' . $account_code)->getUri();
    }
    return $data;
  }

}
