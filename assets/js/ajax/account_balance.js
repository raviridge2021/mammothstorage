function getPaymentAmount() {
    clearAlertMessages();
    // Show the form loader overlay only for the auto-payment form
    $('#make-payment-loader').show();

    // Make an AJAX request to get the payment amount
    $.ajax({
        url: 'account/get_balance',  // Update with your PHP API endpoint
        type: 'POST',
        dataType: 'json',
        success: function (response) {


            sUnitIDs = [];
            sPaymentAmounts = [];

            // Hide the form loader overlay after the response
            $('#make-payment-loader').hide();

            if (response.unit_ids && response.payment) {

                // Iterate over the payments and filter out the ones with non-zero payment
                for (var i = 0; i < response.payment.length; i++) {
                    var amount = parseFloat(response.payment[i]);

                    if (amount > 0) {
                        sUnitIDs.push(response.unit_ids[i]);
                        sPaymentAmounts.push(amount.toFixed(2));
                    }
                }

                // Sum up all the payments from the response and set the final amount
                var totalPayment = response.payment.reduce(function (total, amount) {
                    return total + parseFloat(amount);
                }, 0);

                // Update the payment amount field with the total
                $('#payment-amount').val(totalPayment.toFixed(2)).prop('disabled', false);

                $('#tenant-total-due').text("$" + totalPayment.toFixed(2) || '0.00');


            } else {
                $('#payment-amount').val('Error').prop('disabled', false);
            }
        },
        error: function (xhr, status, error) {

            // Hide the form loader overlay after the response
            $('#make-payment-loader').hide();
            // Handle errors
            console.error('Error fetching payment:', error);
            $('#payment-amount').val('Error').prop('disabled', false);
        }
    });
}



function getNumberOfFuturePeriodsPaymentAmount(numberOfFuturePeriods) {
    clearAlertMessages();
    // Show the form loader overlay only for the auto-payment form
    $('#make-payment-loader').show();


    var formData = {
        numberOfFuturePeriods: numberOfFuturePeriods,

    }
    // Make an AJAX request to get the payment amount
    $.ajax({
        url: 'payment/get_future_charges',  // Update with your PHP API endpoint
        contentType: 'application/json',
        type: 'POST',
        dataType: 'json',
        data: JSON.stringify(formData),
        success: function (response) {

            sUnitIDs = [];
            sPaymentAmounts = [];

            // Hide the form loader overlay after the response
            $('#make-payment-loader').hide();

            if (response.unit_ids && response.payment) {

                // Iterate over the payments and filter out the ones with non-zero payment
                for (var i = 0; i < response.payment.length; i++) {
                    var amount = parseFloat(response.payment[i]);

                    if (amount > 0) {
                        sUnitIDs.push(response.unit_ids[i]);
                        sPaymentAmounts.push(amount.toFixed(2));
                    }
                }

                // Sum up all the payments from the response and set the final amount
                var totalPayment = response.payment.reduce(function (total, amount) {
                    return total + parseFloat(amount);
                }, 0);

                // Update the payment amount field with the total
                $('#payment-amount').val(totalPayment.toFixed(2)).prop('disabled', false);

            } else {
                $('#payment-amount').val('Error').prop('disabled', false);
            }

        },
        error: function (xhr, status, error) {

            // Hide the form loader overlay after the response
            $('#make-payment-loader').hide();
            // Handle errors
            console.error('Error fetching payment:', error);
            $('#payment-amount').val('Error').prop('disabled', false);
        }
    });
}



