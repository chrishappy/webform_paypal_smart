/**
 * @file
 * Script for the webform checkout button
 * 
 * Added through the .module file.
 */

(function ($) {
  
  Drupal.behaviors.webformPaypalCheckout = {
    attach: function (context) {
      var webformPaypalCheckout = this;

      $('.js--webformPaypalCheckoutForm', context).once('webformPaypalCheckout').each(function() {
        var $form = $(this),
            ticketData = $form.data('ticketData'),
            $submitButton = $form.find('.js--webformPaypalCheckoutSubmitButton');
        
//        $submitButton.once('webformPaypalCheckout').on('click', function (e) {
//          e.preventDefault();
//          
//          var $submitButton = $(this);

          // Disable the button
//          $submitButton
//            .attr('disabled', 'true')
//            .after('<span class="ajax-progress-throbber"><div class="throbber">&nbsp;</div></span>');
              
          // @see https://developer.paypal.com/docs/api/orders/v2/#definition-amount_with_breakdown
          var totalAmount = {
            currency_code: 'CAD', // @TODO Allow users to change this
            value: 0, // Fill in when creating itemArray
            breakdown: {
              item_total: {
                value: 0, // Fill in when creating itemArray
                currency_code: 'CAD'
              }
            }
          };
          
          // Create itemArray
          // @see https://developer.paypal.com/docs/api/orders/v2/#definition-item
          var itemArray = [];
        console.log($form.find('[data-drupal-selector^="edit-tickets-items-"]')); // This is running twice per ticket
          $form.find('[data-drupal-selector^="edit-tickets-items-"]').map(function () {
            var $ticket = $(this),
                sku = $ticket.find('[name$="][ticket_type][select]').val(),
                name = $ticket.find('[name$="][name]"]').val() || '';
                        
            if (ticketData.hasOwnProperty(sku)) {
              if (name.length > 0) {  name = ' for ' + name;  }

              itemArray.push({
                "sku": sku,
                "name": ticketData[sku]['name'] + name,
                "quantity": 1,
                "unit_amount": {
                  "value": parseFloat(ticketData[sku]['price']),
                  "currency_code": ticketData[sku]['currency']
                }
              });
              
              totalAmount.value += parseFloat(ticketData[sku]['price']); // @TODO account for currency
            }
          });
        
          // Set amount breakdown
          totalAmount.breakdown.item_total.value = totalAmount.value;
          
          // Wait for paypal api to load
          // @TODO How can we detect whether the PayPal SDK has loaded
          var orderCount = 0;
          var putInOrder = setInterval(function () {
            if (typeof paypal !== 'undefined') {
              var purchaseUnits = [{
                items: itemArray,
                amount: totalAmount
              }];
              webformPaypalCheckout.initPaypalButtons(purchaseUnits);
              clearInterval(putInOrder);
            } else if (orderCount++ > 20) {
              console.error('The Paypal SDK did not load');
              clearInterval(putInOrder);
            }
          }, 700);
          
//          return false;
//        });
        
        $form.after('<div id="checkout__paypal-buttons" />');
        
            /*$items = $form.find('[data-paypal-item]'),
            itemArray = $items.map(function() {  return $(this).data('paypalItem') }).get(),
            $total = $form.find('[data-paypal-amount]'),
            totalAmount = $total.data('paypalAmount'),
            userID = $total.data('userId');*/
        
        

        

      });
    },
    initPaypalButtons: function (purchaseUnits) {
      var webformPaypalCheckout = this,
          paypalOrder = webformPaypalCheckout.createPaypalOrder(purchaseUnits);
    
      // Render the PayPal button into #paypal-button-container
      paypal.Buttons({
        // enableStandardCardFields: true,
        locale: 'en_CA',
        style: {
          height:   40,
          shape:    'rect',
          label:    'pay',
          layout:   'horizontal',
          tagline:  'false',
          branding: 'true',
          fundingicons: 'false',
        },
        // Set up the transaction
        createOrder: function(data, actions) {
          return actions.order.create(paypalOrder);
        },

        // Finalize the transaction
//        onApprove: webformPaypalCheckout.onPaypalApprove,
      }).render('#checkout__paypal-buttons');

      $('body').addClass('paypal-processed');
    },
    createPaypalOrder: function (purchaseUnits) {
      // See https://developer.paypal.com/docs/api/orders/v2/#orders-create-request-body
      var paypalOrder = {
        intent: 'CAPTURE',
        locale: 'en_CA',
        country: 'CA',
        purchase_units: purchaseUnits,
        application_context: {
          brand_name: $('.site-branding__name').text().trim(), //@todo replace
          landing_page: 'BILLING',
          shipping_preference: 'NO_SHIPPING',
          user_action: 'PAY_NOW',
          locale: 'en-CA',
//           return_url: '',
//           cancel_url: '',
        },
        payer: {
           email_address: 'johndoe@example.com',
           name: {  //@todo replace
             given_name: 'John', //$('.field-user--field-first-name .field__item').text().trim(),
             surname: 'Doe', // $('.field-user--field-last-name .field__item').text().trim()
          },
          address: {
            address_line_1: '',
            address_line_2: '',
            admin_area_2: '', // City
            admin_area_1: 'BC', // Province
            postal_code: '',
            country_code: 'CA'
          }
        }
      };

//      var phone = $('.field-user--field-phone .field__item').text().replace(/[^\d]/g, '');
//
//      if (1 <= phone.length && phone.length <= 14) {
//        paypalOrder.payer.phone  = {
//          phone_type: 'MOBILE',
//          phone_number: {
//            national_number: phone //@todo replace
//          }
//        };
//      }
      
      console.log(JSON.stringify(paypalOrder));
      
      return paypalOrder;
    },
    onPaypalApprove: function(data, actions) {
      return actions.order.capture().then(function(details) {

        // Show a success message to the buyer
        alert('Thank you. Please wait as we store your payment.');

        // Prevent unloading
        $(window).on('beforeunload.waitForPaypal', function () {
          return 'Please wait as we record your payment.';
        });

        console.log(details);

        return $.ajax({
          url: '/paypal/complete',
          type: 'POST',
          dataType: 'json',
          data: {
            orderID: data.orderID,
            userID: userID,
          },
          success: function (data, textStatus) {
            alert('Success, your payment has been stored.');

            // All the window to reload
            $(window).off('beforeunload.waitForPaypal');

            // Reload the page
            location.reload(true);
          },
          fail: function(xhr, textStatus, errorThrown){
            alert('Sorry, your payment has not been stored. Please contact us for more information.');

            // All the window to reload
            $(window).off('beforeunload.waitForPaypal');
          }
          })
      });
    }
  }

})(jQuery)