function loadPaymentTypes() {
    console.log("Loading payment types..."); // Debugging message to verify the function is called
    $.ajax({
        url: 'payment/get_payment_types',  // Your new API route
        type: 'POST',
        dataType: 'json',
        success: function (response) {
            console.log("Response received: ", response); // Log the response to ensure the API is working
            if (response.success && response.payment_types) {
                // Iterate over each credit card dropdown and populate them with payment types
                $('.credit-card-type').each(function () {
                    var $dropdown = $(this);
                    // Clear existing options
                    $dropdown.empty();

                    // Populate dropdown with payment types
                    $.each(response.payment_types, function (index, paymentType) {
                        var paymentTypeData;

                        // Check the description to set the data attribute
                        if (paymentType.sPmtTypeDesc === 'Visa') {
                            paymentTypeData = 6;
                        } else if (paymentType.sPmtTypeDesc === 'Master Card') {
                            paymentTypeData = 5;
                        }

                        // Append option to the dropdown with the additional data attribute
                        $dropdown.append(
                            $('<option>', {
                                value: paymentType.PmtTypeID, // Set PmtTypeID as the value
                                'data-payment-type': paymentTypeData // Set the data-payment-type attribute
                            }).text(paymentType.sPmtTypeDesc) // Set sPmtTypeDesc as the display text
                        );
                    });


                    console.log("Dropdown populated successfully for", $dropdown);
                });
            } else {
                console.error('Error fetching payment types or no payment types found.');
            }
        },
        error: function (xhr, status, error) {
            console.error('Error:', error); // Log any errors
        }
    });
}