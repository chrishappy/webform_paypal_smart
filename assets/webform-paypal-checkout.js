/**
 * @file
 * Script for the webform checkout button
 * 
 * Added through the .module file.
 */

(function ($) {

  Drupal.behaviors.webformPaypalCheckout = {
    // sid: 0, // Did not work, dynamically find it after saving draft

    paypalContainerId: 'webform-smart-paypal__paypal-button-container',
    draftButtonSelector: '[data-drupal-selector="edit-actions-draft"], [data-drupal-selector="edit-actions"] .webform-button--draft',
    ticketsRowSelector: 'tr[data-drupal-selector^="edit-tickets-items-"]',
    taxAmount: 0.05,
    attach: function (context) {
      var webformPaypalCheckout = this;
      $('.js--webformPaypalCheckoutForm', context).once('webformPaypalCheckout').each(function () {
        var $form = $(this);
        // $submitButton = $form.find('.js--webformPaypalCheckoutSubmitButton');

        webformPaypalCheckout.sid = $form.data('sid'); // @TODO Must ensure that the form is draft
        // Save webform as draft if data-sid not present?

        // Wait for paypal api to load
        // @TODO How can we detect whether the PayPal SDK has loaded
        var orderCount = 0;
        var putInOrder = setInterval(function () {
          if (typeof paypal !== 'undefined') {
            // @TODO Prevent function from running twice when in a Drupal modal
            // Check if there is already a button
            if ($('#' + webformPaypalCheckout.paypalContainerId).children().length === 0
              && $form.find(webformPaypalCheckout.ticketsRowSelector).length !== 0) {
              webformPaypalCheckout.initPaypalButtons();
            }
            clearInterval(putInOrder);
          } else if (orderCount++ > 20) {
            console.error('The Paypal SDK did not load');
            clearInterval(putInOrder);
          }
        }, 700);

        $form.parent().after('<div id="' + webformPaypalCheckout.paypalContainerId + '" />');
      });
    },
    initPaypalButtons: function () {
      var webformPaypalCheckout = this,
        $form = $('.js--webformPaypalCheckoutForm'); // Don't cache due to https://stackoverflow.com/q/42053775

      // Render the PayPal button into #paypal-button-container
      paypal.Buttons({
        // enableStandardCardFields: true,
        locale: 'en_CA',
        style: {
          height: 40,
          shape: 'rect',
          label: 'pay',
          layout: 'horizontal',
          tagline: 'false',
          branding: 'true',
          fundingicons: 'false',
        },
        onClick: function (data, actions) { // Validate the button click
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
          else if (false) {
            // @TODO check if there are enough tickets
            // Reserve tickets before initating order
          }
          else {
            return actions.resolve();
          }

          return actions.reject();
        },
        // Set up the transaction
        createOrder: function (data, actions) {
          // Save webform as draft
          // @TODO save webform as draft using AJAX, perhaps async?
          $form.find(webformPaypalCheckout.draftButtonSelector).click();

          // Create the paypal order on demand
          try {
            var paypalOrder = webformPaypalCheckout.createPaypalOrder();
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
    createPaypalOrder: function () {

      var webformPaypalCheckout = this,
        $form = $('.js--webformPaypalCheckoutForm'), // Don't cache due to https://stackoverflow.com/q/42053775
        ticketData = $form.data('ticketData'),
        taxAmount = this.taxAmount;

      // @see https://developer.paypal.com/docs/api/orders/v2/#definition-amount_with_breakdown
      var totalAmount = {
        currency_code: 'CAD', // @TODO Allow users to change this
        value: 0, // Fill in when creating itemArray
        breakdown: {
          item_total: {
            value: 0, // Fill in when creating itemArray
            currency_code: 'CAD' // @TODO Allow users to change this
          },
          tax_total: {
            value: 0, // Fill in after calculating item_total
            currency_code: 'CAD' // @TODO Allow users to change this
          }
        }
      };

      // Create itemArray
      // @see https://developer.paypal.com/docs/api/orders/v2/#definition-item
      var itemArray = [];
      // $form.find('tr[data-drupal-selector^="edit-tickets-items-"]').map(function () {
      $form.find(webformPaypalCheckout.ticketsRowSelector).map(function () {
        var $ticket = $(this),
          sku = $ticket.find('[name$="][ticket_type][select]').val(),
          name = $ticket.find('[name$="][name]"]').val() || '',
          itemPrice = parseFloat(ticketData[sku]['price']),
          itemTax = parseFloat((itemPrice * taxAmount).toFixed(2));

        if (ticketData.hasOwnProperty(sku)) {
          if (name.length > 0) { name = ' for ' + name; } // Format: [ticket] for [name]

          itemArray.push({
            sku: sku,
            name: ticketData[sku]['name'] + name,
            quantity: 1,
            unit_amount: {
              value: itemPrice,
              currency_code: ticketData[sku]['currency']
            },
            tax: {
              value: itemTax,
              currency_code: ticketData[sku]['currency']
            }
          });

          totalAmount.breakdown.item_total.value += itemPrice;
          totalAmount.breakdown.tax_total.value += itemTax;
        }
      });

      // Set amount breakdown
      totalAmount.breakdown.tax_total.value = parseFloat(totalAmount.breakdown.tax_total.value.toFixed(2)); // Fix percision
      totalAmount.breakdown.item_total.value = parseFloat(totalAmount.breakdown.item_total.value.toFixed(2)); // Fix percision

      totalAmount.value = totalAmount.breakdown.item_total.value + totalAmount.breakdown.tax_total.value;
      totalAmount.value = parseFloat(totalAmount.value.toFixed(2)); // Fix percision

      if (totalAmount.value <= 0) {
        throw 'No items are being sold';
      }

      // See https://developer.paypal.com/docs/api/orders/v2/#orders-create-request-body
      var paypalOrder = {
        intent: 'CAPTURE',
        locale: 'en_CA',
        country: 'CA',
        purchase_units: [{
          items: itemArray,
          amount: totalAmount
        }],
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
    onPaypalApprove: function (data, actions) {
      return actions.order.capture().then(function (details) {

        // Show a success message to the buyer
        //        alert('Thank you. Please wait as we store your payment.');

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
            submissionID: $('.js--webformPaypalCheckoutForm').data('sid'), // @TODO improve getting sid || Drupal.behaviors.webformPaypalCheckout.sid,
          },
          success: function (data, textStatus) {
            // alert('Success, your payment has been stored.');

            // All the window to reload
            $(window).off('beforeunload.waitForPaypal');

            $(window).on('beforeunload', function () {
              $(window).scrollTop(0);
            });

            // Reload the page
            window.location.reload(window.location.href + '#random' + Math.random());
          },
          error: function (xhr, textStatus, errorThrown) {
            console.error('Error: ' + textStatus + ' ' + JSON.stringify(errorThrown));
            // alert('Sorry, your payment has not been stored. Please contact us for more information.');

            // All the window to reload
            $(window).off('beforeunload.waitForPaypal');
          }
        })
      });
    }
  }

})(jQuery)