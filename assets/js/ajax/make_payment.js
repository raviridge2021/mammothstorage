$(document).ready(function () {
    // Function to populate years for specific select elements
    function populateYears($expiryYearSelect) {
        var currentYear = new Date().getFullYear();
        $expiryYearSelect.empty();

        for (var i = 0; i <= 10; i++) {
            var yearOption = $('<option></option>').val(currentYear + i).text(currentYear + i);
            $expiryYearSelect.append(yearOption);
        }
    }

    // Function to populate months based on the selected year for specific select elements
    function populateMonths($expiryMonthSelect, $expiryYearSelect) {
        var currentYear = new Date().getFullYear();
        var currentMonth = new Date().getMonth() + 1; // JavaScript months are 0-based, so add 1
        var selectedYear = parseInt($expiryYearSelect.val(), 10); // Ensure selected year is an integer

        $expiryMonthSelect.empty(); // Clear previous options

        // If the selected year is the current year, only show months from the current month onward
        if (selectedYear === currentYear) {
            for (var i = currentMonth; i <= 12; i++) {
                var monthOption = $('<option></option>').val(('0' + i).slice(-2)).text(('0' + i).slice(-2));
                $expiryMonthSelect.append(monthOption);
            }
        } else {
            // If it's a future year, show all months (1-12)
            for (var i = 1; i <= 12; i++) {
                var monthOption = $('<option></option>').val(('0' + i).slice(-2)).text(('0' + i).slice(-2));
                $expiryMonthSelect.append(monthOption);
            }
        }
    }

    // Initialize years and months for all instances on page load
    function initializeExpiryFields() {
        $('.expiry-year').each(function () {
            populateYears($(this)); // Populate years for each .expiry-year select element
        });

        $('.expiry-year').each(function () {
            var $expiryYearSelect = $(this);
            var $expiryMonthSelect = $expiryYearSelect.closest('form').find('.expiry-month');
            populateMonths($expiryMonthSelect, $expiryYearSelect); // Populate months for each .expiry-month select
        });
    }

    // When a year changes, update the months accordingly
    $(document).on('change', '.expiry-year', function () {
        var $expiryYearSelect = $(this); // Get the selected year
        var $expiryMonthSelect = $(this).closest('form').find('.expiry-month');
        populateMonths($expiryMonthSelect, $expiryYearSelect); // Update months based on the new selected year
    });

    // Call this function on page load to initialize expiry fields
    initializeExpiryFields();

    // Handle visibility of additional fields based on payment option selection
    $('input[name="payment-option"]').on('change', function () {

        $('#number-of-months-group').hide();
        $('#add-money-group').hide();

        var selectedOption = $('input[name="payment-option"]:checked').val();

        if (selectedOption === 'current-due') {
            getPaymentAmount();
        } else if (selectedOption === 'current-plus-months') {
            $('#number-of-months').val(1);
            $('#number-of-months-group').show();
            $('#add-money-group').hide();
            getNumberOfFuturePeriodsPaymentAmount(1);
        } else if (selectedOption === 'current-plus-next') {
            $('#number-of-months-group').hide();
            $('#add-money-group').hide();
            getNumberOfFuturePeriodsPaymentAmount(1);
        } else {
            $('#number-of-months-group').hide();
            $('#add-money-group').hide();
        }
    });

    $('#number-of-months').change(function () {
        var numberOfFuturePeriods = $('#number-of-months').val();
        getNumberOfFuturePeriodsPaymentAmount(numberOfFuturePeriods);
    });

    // Form submission with spinner and AJAX
    $('#make-payment-form').on('submit', function (e) {
        e.preventDefault();

        // Clear existing messages
        $('#response-message').removeClass('alert-success alert-danger').hide();

        // Validate form fields
        var creditCardTypeID = $('#credit-card-type').val();
        var creditCardNum = $('#credit-card-number').val();
        var creditCardExpire = $('#credit-card-expiry-year').val() + '-' + $('#credit-card-expiry-month').val();
        var creditCardHolderName = $('#credit-card-holder').val();
        var creditCardCVV = $('#credit-card-cvv').val();
        var selectedOption = $('input[name="payment-option"]:checked').val();
        var paymentOptions = 0;


        // Get the selected option's data-payment-type attribute for credit card type
        var selectedCreditCardOption = $('#credit-card-type').find(':selected');
        var paymentTypeData = selectedCreditCardOption.data('payment-type'); // Retrieves the data-payment-type attribute


        if (selectedOption === 'current-due') {

            paymentOptions = 1;

        } else if (selectedOption === 'current-plus-months' || selectedOption === 'current-plus-next') {

            paymentOptions = 2;

        }


        // Check if all fields are filled
        if (!creditCardTypeID || !creditCardNum || !creditCardHolderName || !creditCardExpire || !creditCardCVV) {
            $('#response-message')
                .addClass('alert-danger')
                .text('All fields are required. Please fill out all the information.')
                .show();
            return; // Stop form submission if validation fails
        }


        if (paymentOptions < 1) {

            $('#response-message')
                .addClass('alert-danger')
                .text('Please select a payment option to proceed. You can choose to pay the current due, the next month, or multiple months in advance.')
                .show();
            return; // Stop form submission if validation fails

        }

        // Ensure that paymentTypeData exists (data-payment-type attribute)
        if (!paymentTypeData) {
            $('#response-message')
                .addClass('alert-danger')
                .text('Invalid credit card type selected.')
                .show();
            return; // Stop form submission if no valid credit card type is selected
        }

        // Validate sUnitIDs and sPaymentAmounts
        if (!sUnitIDs || !sPaymentAmounts) {
            $('#response-message')
                .addClass('alert-danger')
                .text('Error: No valid units or payment amounts found. Please try again.')
                .show();
            return; // Stop form submission if validation fails
        }

        // Ensure that at least one payment amount is greater than 0
        var hasDuePayments = sPaymentAmounts.some(function (amount) {
            return parseFloat(amount) > 0;
        });

        if (paymentOptions == 1) {
            // If no valid dues, display a polite informational message
            if (!hasDuePayments) {
                $('#response-message')
                    .addClass('alert-info')
                    .text('There are no outstanding payments at this time. No action is needed.')
                    .show();
                return; // Stop form submission if validation fails
            }
        }

        // Show the form loader overlay only for the auto-payment form
        $('#make-payment-loader').show();

        // Convert the arrays to comma-separated strings
        var sUnitIDsString = sUnitIDs.join(', ');
        var sPaymentAmountsString = sPaymentAmounts.join(', ');

        var formData = {
            creditCardTypeID: paymentTypeData,
            creditCardNum: creditCardNum,
            creditCardExpire: creditCardExpire,
            creditCardHolderName: creditCardHolderName,
            creditCardCVV: creditCardCVV,
            paymentOptions: paymentOptions,
            sUnitIDs: sUnitIDsString,
            sPaymentAmounts: sPaymentAmountsString
        };

        // Check if paymentOptions is equal to 2
        if (paymentOptions == 2) {
            var numberOfMonths = $('#number-of-months').val(); // Get the value of number of months
            formData.numberOfMonths = numberOfMonths; // Add numberOfMonths to formData
        }


        // AJAX call to submit the form
        $.ajax({
            url: site_url + 'payment/make_payment',  // Your controller function route
            type: 'POST',
            contentType: 'application/json',
            dataType: 'json',
            data: JSON.stringify(formData),
            success: function (response) {
                // Hide the form loader overlay after the response
                $('#make-payment-loader').hide();

                // Clear existing messages
                $('#response-message').removeClass('alert-success alert-danger').hide();

                // Handle response
                if (response.success) {

                    sUnitIDs = [];
                    sPaymentAmounts = [];


                    $('#response-message')
                        .addClass('alert-success')
                        .text('Your payment has been successfully processed! Thank you for your prompt payment.')
                        .show();

                    getDueBalance();

                } else {
                    $('#response-message')
                        .addClass('alert-danger')
                        .text('Error: ' + response.error)
                        .show();
                }
            },
            error: function (xhr, status, error) {
                // Hide the form loader overlay on error
                $('#make-payment-loader').hide();

                // Show error message
                $('#response-message')
                    .addClass('alert-danger')
                    .text('Error submitting form: ' + error)
                    .show();
            }
        });
    });

});
