/**
 * @file
 * dontSubmitAgain
 *
 * Disables submit button and add ajax spinner
 *
 * Added through the src/Plugin/WebformPaypalSmartButtons::formAlter
 */

(function ($) {
  
  Drupal.behaviors.dontSubmitAgain = {
    attach: function (context, settings) {
      $('.js-dont-submit-again [type="submit"]', context).click(function() {
        var $this = $(this);
        
        $this
          .attr('disabled', 'true')
          .addClass('align-left')
          .after('<div class="ajax-progress-throbber"><div class="throbber">&nbsp;</div></div>');

        $this.closest('form').submit();
      });      
    }
  }

})(jQuery);;