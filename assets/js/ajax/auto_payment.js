function loadAutoPayment() {

    // Clear existing messages
    $('#response-message2').removeClass('alert-success alert-danger').hide();

    // Show the loading spinner
    $('#units-loader').show();

    // Hide the unit dropdown until data is fetched
    $('#add-to-account-unit').hide();

    // Make an AJAX request to get the units
    $.ajax({
        url: 'units/get_units',  // Update with your API endpoint
        type: 'POST',
        dataType: 'json',
        success: function (response) {
            if (response.unit_ids && response.ledger_ids && response.unit_names) {
                // Clear any existing options
                $('#add-to-account-unit').empty();

                // Append a default "Please select" option with no value
                $('#add-to-account-unit').append(
                    $('<option>', {
                        value: '',  // Empty value
                        selected: true,  // Make it selected by default
                        disabled: true  // Prevents selection again
                    }).text('Please select')  // Display text is "Please select"
                );

                // Append options with additional data attributes (ledger_id and unit_id)
                $.each(response.unit_ids, function (index, unit_id) {
                    var ledger_id = response.ledger_ids[index];
                    var unit_name = response.unit_names[index];

                    $('#add-to-account-unit').append(
                        $('<option>', {
                            value: unit_id,  // Value is unit_id
                            'data-ledger-id': ledger_id  // Store ledger_id in data attribute
                        }).text(unit_name)  // Display text is unit_name
                    );
                });

                // Hide the loading spinner and show the dropdown
                $('#units-loader').hide();
                $('#add-to-account-unit').show();
            } else {
                console.error('Error: No units found in response.');
                $('#units-loader').hide();
            }
        },
        error: function (xhr, status, error) {
            console.error('Error fetching units:', error);
            $('#units-loader').hide();
        }
    });
}



$(document).ready(function () {
    // Form submission with spinner and AJAX
    $('#auto-payment-form').on('submit', function (e) {
        e.preventDefault();

        // Clear existing messages
        $('#response-message2').removeClass('alert-success alert-danger').hide();

        // Validate form fields
        var ledgerID = $('#add-to-account-unit').find(':selected').data('ledger-id');
        var creditCardTypeID = $('#credit-card-type2').val();
        var creditCardNum = $('#credit-card-number2').val();
        var creditCardExpire = $('#expiry-year2').val() + '-' + $('#expiry-month2').val();
        var creditCardHolderName = $('#credit-card-holder2').val();
        var autoBillType = $('input[name="payment-auto-renew-option"]:checked').val();

        // Ensure ledgerID is valid
        if (!ledgerID || ledgerID === 'undefined') {
            $('#response-message2')
                .addClass('alert-danger')
                .text('Please select a valid unit to proceed with your payment.')
                .show();
            return;
        }

        // Check if all fields are filled
        if (
            !ledgerID ||  // Ensure ledgerID is valid
            !creditCardTypeID || creditCardTypeID.trim() === "" ||  // Ensure credit card type is selected
            !creditCardNum || creditCardNum.trim() === "" ||  // Ensure credit card number is filled
            !creditCardHolderName || creditCardHolderName.trim() === "" ||  // Ensure credit card holder name is filled
            !creditCardExpire || creditCardExpire.trim() === "" ||  // Ensure credit card expiration is filled
            !autoBillType || autoBillType.trim() === ""  // Ensure a billing option is selected
        ) {
            $('#response-message2')
                .addClass('alert-danger')
                .text('All fields are required. Please fill out all the information.')
                .show();
            return; // Stop form submission if validation fails
        }

        // Show the form loader overlay only for the auto-payment form
        $('#auto-payment-loader').show();

        // Gather form data
        var formData = {
            ledgerID: ledgerID,
            creditCardTypeID: creditCardTypeID,
            creditCardNum: creditCardNum,
            creditCardExpire: creditCardExpire,
            creditCardHolderName: creditCardHolderName,
            autoBillType: autoBillType
        };

        // AJAX call to submit the form
        $.ajax({
            url: site_url + 'payment/update_auto_payment',  // Your controller function route
            type: 'POST',
            contentType: 'application/json',
            dataType: 'json',
            data: JSON.stringify(formData),
            success: function (response) {
                // Hide the form loader overlay after the response
                $('#auto-payment-loader').hide();

                // Clear existing messages
                $('#response-message2').removeClass('alert-success alert-danger').hide();

                // Handle response
                if (response.success) {
                    $('#response-message2')
                        .addClass('alert-success')
                        .text('Auto payment details saved successfully!')
                        .show();
                } else {
                    $('#response-message2')
                        .addClass('alert-danger')
                        .text('Error: ' + response.error)
                        .show();
                }
            },
            error: function (xhr, status, error) {
                // Hide the form loader overlay on error
                $('#auto-payment-loader').hide();

                // Show error message
                $('#response-message2')
                    .addClass('alert-danger')
                    .text('Error submitting form: ' + error)
                    .show();
            }
        });
    });
});
