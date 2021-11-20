<?php
/**
 * @file
 * Contains the custom hooks for webform_paypal_smart
 */


/**
 * Runs after a webform has been saves as a draft (before Paypal) or completed (after Paypal)
 * 
 * You can use $webform_submission->isDraft() or $webform_submission->isCompleted() to detect if the webform submission
 * has been submitted yet or not
 * 
 * @param \Drupal\webform\WebformSubmissionInterface $webform_submission
 *   A webform submission.
 * @param bool $update
 *   TRUE if the entity has been updated, or FALSE if it has been inserted.
 */
function hook_webform_paypal_smart_submission_post_save(WebformSubmissionInterface $webform_submission, string $webform_id, bool $update = TRUE) {
  switch ($webform_id) {
    case 'my_contact_form':
      if ($webform_submission->isCompleted()) {
        $webform_data = $webform_submission->getData();
      }
      break;
  }
}