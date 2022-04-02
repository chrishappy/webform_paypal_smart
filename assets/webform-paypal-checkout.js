/**
 * @file
 * Script for the webform checkout button
 * 
 * Added through the .module file.
 */

(function ($) {

  Drupal.webformPaypalCheckout = {
    handlers: {}
  }

  Drupal.behaviors.webformPaypalCheckout = {
    // sid: 0, // Did not work, dynamically find it after saving draft

    parentContainerClass: 'webform-paypal-checkout--other-actions',
    paypalContainerId: 'webform-smart-paypal__paypal-button-container',
    totalAmountsClass: 'webform-smart-paypal__total-amounts-container',
    draftButtonSelector: '[data-drupal-selector="edit-actions-draft"], [data-drupal-selector="edit-actions"] .webform-button--draft',
    orderFunctionAttribute: 'paypalCheckoutWebformId',
    handler: null,
    attach: function (context) {
      var webformPaypalCheckout = this;
      $('.js--webformPaypalCheckoutForm', context).once('webformPaypalCheckout').each(function () {
        var $form = $(this);
        // $submitButton = $form.find('.js--webformPaypalCheckoutSubmitButton');

        webformPaypalCheckout.sid = $form.data('sid'); // TODO Must ensure that the form is draft
        // Save webform as draft if data-sid not present?

        // Wait for paypal api to load
        // TODO How can we detect whether the PayPal SDK has loaded
        var orderCount = 0;
        var putInOrder = setInterval(function () {
          if (typeof paypal !== 'undefined') {
            // TODO Prevent function from running twice when in a Drupal modal
            // Check if there is already a button

             // TODO Check if form is properly initatilized
            if ($('#' + webformPaypalCheckout.paypalContainerId).children().length === 0) {
              webformPaypalCheckout.initPaypalButtons();
            }
            clearInterval(putInOrder);
          } else if (orderCount++ > 20) {
            console.error('The Paypal SDK did not load');
            clearInterval(putInOrder);
          }
        }, 700);

        if ($form.parent().siblings('.' + webformPaypalCheckout.parentContainerClass).length === 0) {
          $form.parent().after('<div class="' + webformPaypalCheckout.parentContainerClass + '"> ' +
                                  '<div class="' + webformPaypalCheckout.totalAmountsClass + '"></div>' + 
                                  '<div id="' + webformPaypalCheckout.paypalContainerId + '"></div>' + 
                              '</div>');
        }

      // Set handler
      var orderFunctionToCall = $form.data(webformPaypalCheckout.orderFunctionAttribute);
      if (Drupal.webformPaypalCheckout.handlers.hasOwnProperty(orderFunctionToCall)) {
        webformPaypalCheckout.handler = Drupal.webformPaypalCheckout.handlers[orderFunctionToCall];
      }
      else {
        throw 'No handler found with key: ' + orderFunctionToCall;
      }

      if (webformPaypalCheckout.handlerHasFunction('initTotalSection')) {
        webformPaypalCheckout.handler.initTotalSection($form, '.' + webformPaypalCheckout.totalAmountsClass);
      }
        
      });
    },
    initPaypalButtons: function () {
      var webformPaypalCheckout = this,
        $form = $('.js--webformPaypalCheckoutForm'); // Don't cache (as a parameter) due to https://stackoverflow.com/q/42053775

      // Render the PayPal button into #paypal-button-container
      paypal.Buttons({
        // enableStandardCardFields: true,
        locale: 'en_CA',
        style: {
          height: 40,
          shape: 'rect',
          label: 'pay',
          // layout: 'horizontal',
          tagline: 'false',
          branding: 'true',
          fundingicons: 'false',
        },
        onClick: function (data, actions) { // Validate the button click
          var $form = $('.js--webformPaypalCheckoutForm');
          
          // Save webform as draft
          // TODO better way to save webform as draft using AJAX, perhaps async?
          $form.find(webformPaypalCheckout.draftButtonSelector).click();

          // Source: https://stackoverflow.com/a/48267035 (Loilo <https://stackoverflow.com/u/2048874>)
          if (!$form[0].checkValidity()) {
            // if (!$form[0].reportValidity()) {
            // Create the temporary button, click and remove it
            var $tmpSubmit = $('<input type="submit">')
            $form.append($tmpSubmit)
            $tmpSubmit.click()
            $tmpSubmit.remove();
            console.log("Form is not valid");
          }
          else if (webformPaypalCheckout.handlerHasFunction('customValidation')) {
            // Need to save the form some other how...

            return webformPaypalCheckout.handlerCheckValidation(data, actions);
          }
          else if (false) {
            // TODO check if there are enough tickets
            // Reserve tickets before initating order
          }
          else {            
            return actions.resolve();
          }

          return actions.reject();
        },
        // Set up the transaction
        createOrder: function (data, actions) {
          // Create the paypal order on demand
          try {
            var paypalOrder = webformPaypalCheckout.handlerCreatePaypalOrder();
            return actions.order.create(paypalOrder);
          } catch (e) {
            console.error(e);
          }
        },

        // Finalize the transaction
        onApprove: webformPaypalCheckout.onPaypalApprove,
      }).render('#' + webformPaypalCheckout.paypalContainerId);

      $('body').addClass('paypal-processed');
    },
    handlerHasFunction: function(func) {
      return (this.handler && typeof this.handler[func] == 'function');
    },
    handlerGetProperty: function (prop) {
      if (this.handler && this.handler.hasOwnProperty(prop)) {
        return this.handler[prop];
      }
      return null;
    },
    handlerCheckValidation: function (data, actions) {
      if (this.handlerHasFunction('customValidation')) {
        var $form = $('.js--webformPaypalCheckoutForm'); // Don't cache due to https://stackoverflow.com/q/42053775

        return this.handler.customValidation($form, data, actions);
      }
      else {
        throw "No custom validation function. Prevent this error by using hasValidation() first.";
      }
    },
    handlerCreatePaypalOrder: function () {
      var $form = $('.js--webformPaypalCheckoutForm'); // Don't cache due to https://stackoverflow.com/q/42053775

      if (this.handlerHasFunction('createOrder')) {
        var defaultOrderObject = JSON.parse(JSON.stringify(Drupal.webformPaypalCheckout.defaultOrder)),
            orderReference = $form.find('[data-drupal-selector="edit-paypal-webform-connector-value"]').val();

        var order = this.handler.createOrder($form, defaultOrderObject);

        if (order.purchase_units.length >= 1 && !order.purchase_units[0].hasOwnProperty('reference_id')) {
          order.purchase_units[0].reference_id = orderReference;
        }
        

        console.log(JSON.stringify(order));

        return order;
      }

      throw "No CreateOrderHook found";
    },
    onPaypalApprove: function (data, actions) {
      // Use the reference ID to find the appropiate webform
      var $form = $('.js--webformPaypalCheckoutForm'),
          orderReferenceId = $form.find('[data-drupal-selector="edit-paypal-webform-connector-value"]').val()

      return actions.order.capture().then(function (details) {

        // Show a success message to the buyer
        //        alert('Thank you. Please wait as we store your payment.');
        var $ajaxProgress = $(Drupal.theme('ajaxProgressIndicatorFullscreen'));
        $('body').append($ajaxProgress);

        // Prevent unloading
        $(window).on('beforeunload.waitForPaypal', function () {
          return 'Please wait as we record your payment.';
        });

        return Drupal.webformPaypalCheckout.getAjaxRequest(data, orderReferenceId, $ajaxProgress);
      });
    }
  }

  Drupal.webformPaypalCheckout.getAjaxRequest = function (data, referenceId, $ajaxProgress) {
    return $.ajax({
      url: '/paypal/complete',
      type: 'POST',
      dataType: 'json',
      data: {
        orderID: data.orderID,
        referenceID: referenceId,
      },
      success: function (data, textStatus) {
        // Remove ajax throbber
        $ajaxProgress.remove();

        // All the window to reload
        $(window).off('beforeunload.waitForPaypal');

        $(window).on('beforeunload', function () {
          $(window).scrollTop(0);
        });

        // Reload the page
        var potentialRedirect = Drupal.behaviors.webformPaypalCheckout.handlerGetProperty('redirectUrl');
        var destination = getQueryVariable('dest') || potentialRedirect || false;
        
        if (destination) {
          window.location.replace(destination + '#r' + Math.random().toFixed(2));
        }
        else {
          window.location.reload(window.location.href  + '#r' + Math.random().toFixed(2));
        }
      },
      error: function (xhr, textStatus, errorThrown) {
        // Remove ajax throbber
        $ajaxProgress.remove();

        console.error('Could not process order: ' + textStatus);
        console.error(errorThrown);
        console.error(xhr);
        alert('Sorry, there was an error. Please contact us for more information.');

        // All the window to reload
        $(window).off('beforeunload.waitForPaypal');
      }
    });
  }

  // See https://developer.paypal.com/docs/api/orders/v2/#orders-create-request-body
  Drupal.webformPaypalCheckout.defaultOrder = {
    intent: 'CAPTURE',
    locale: 'en_CA',
    country: 'CA',
    purchase_units: [],
    application_context: {
      brand_name: $('.site-branding__name').text().trim(), //TODO replace
      landing_page: 'BILLING',
      shipping_preference: 'NO_SHIPPING',
      user_action: 'PAY_NOW',
      locale: 'en-CA',
      //           return_url: '',
      //           cancel_url: '',
    },
    payer: {
      // email_address: '',
      name: {
        // given_name: '',
        // surname: '',
      },
      address: {
        address_line_1: '',
        address_line_2: '',
        admin_area_2: '', // City
        admin_area_1: 'BC', // Province
        postal_code: '',
        country_code: 'CA',
      }
    }
  }

  Drupal.webformPaypalCheckout.setPhone = function (order, phoneNumber) {
    phone = phoneNumber.replace(/[^\d]/g, '');

     if (1 <= phone.length && phone.length <= 14) {
      order.payer.phone  = {
         phone_type: 'MOBILE',
         phone_number: {
           national_number: phone //TODO replace
         }
       };
     }
  }

  Drupal.webformPaypalCheckout.setNameAndEmail = function (order, name, email) {
    order.payer.email = email;

    if (!Array.isArray(name)) {
      name = name.split(' ');
    }

    if (name.length == 2) {
      order.payer.name.given_name = name[0];
      order.payer.name.surname = name[1];
    }
    else {
      order.payer.name.given_name = name.join(' ');
    }
  }

  // https://css-tricks.com/snippets/javascript/get-url-variables/
  function getQueryVariable(variable)
  {
    var query = window.location.search.substring(1);
    var vars = query.split("&");
    for (var i=0;i<vars.length;i++) {
            var pair = vars[i].split("=");
            if(pair[0] == variable){return pair[1];}
    }
    return '';
  }

})(jQuery)