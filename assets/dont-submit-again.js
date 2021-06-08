/**
 * @file
 * dontSubmitAgain
 *
 * Disables submit button and add ajax spinner
 *
 * Added through the .module file (hook_form_alter)
 */

(function ($) {
  
  Drupal.behaviors.dontSubmitAgain = {
    attach: function (context, settings) {
  
      /*=======================================
         Bid Form
        =======================================*/
      $('.dontSubmitAgain [type="submit"]', context).click(function() {
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