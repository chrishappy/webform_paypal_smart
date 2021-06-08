/**
 * @file
 * Script for the webform checkout button
 * 
 * Added through the .module file.
 */

(function ($) {
  
  Drupal.behaviors.webformPaypalCheckout = {
    attach: function (context) {
      $('.js--webformPaypalCheckout', context).once('webformPaypalCheckout').each(function() {
        var $this = $(this),
            $items = $this.find('[data-paypal-item]'),
            itemArray = $items.map(function() {  return $(this).data('paypalItem') }).get(),
            $total = $this.find('[data-paypal-amount]'),
            totalObject = $total.data('paypalAmount'),
            userID = $total.data('userId');
        
        var paypalOrder = {
          locale: 'en_CA',
          country: 'CA',
          purchase_units: [{
            items: itemArray,
            amount: totalObject
          }],
          application_context: {
            brand_name: $('.site-branding__name-link').text().trim(), //@todo replace
            landing_page: 'BILLING',
            shipping_preference: 'NO_SHIPPING',
            user_action: 'PAY_NOW',
            locale: 'en-CA',
            // return_url: '',
            // cancel_url: '',
          },
          payer: {
            // email_address: 'johndoe@example.com',
            name: {  //@todo replace
               given_name: $('.field-user--field-first-name .field__item').text().trim(),
               surname: $('.field-user--field-last-name .field__item').text().trim()
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
        
        var phone = $('.field-user--field-phone .field__item').text().replace(/[^\d]/g, '');
          
        if (1 <= phone.length && phone.length <= 14) {
          paypalOrder.payer.phone  = {
            phone_type: 'MOBILE',
            phone_number: {
              national_number: phone //@todo replace
            }
          };
        }

        // Wait for paypal api to load
        // @TODO How can we detect whether the PayPal SDK has loaded
        var orderCount = 0;
        var putInOrder = setInterval(function () {
          if (typeof paypal !== 'undefined') {
            initPaypalOrder();
            clearInterval(putInOrder);
          } else if (orderCount++ > 20) {
            console.error('The Paypal SDK did not load');
            clearInterval(putInOrder);
          }
        }, 700);

        function initPaypalOrder() {
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
            onApprove: function(data, actions) {
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
          }).render('#checkout__paypal-buttons');

          $('body').addClass('paypal-processed');
        }
        

      });
    }
  }

})(jQuery)