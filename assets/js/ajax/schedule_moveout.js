function loadAllUnits() {

    // Clear existing messages
    $('#response-message4').removeClass('alert-success alert-danger').hide();

    // Show the loading spinner
    $('#units-loader2').show();

    // Hide the unit dropdown until data is fetched
    $('#add-to-account-unit2').hide();

    // Make an AJAX request to get the units
    $.ajax({
        url: 'units/get_units',  // Update with your API endpoint
        type: 'POST',
        dataType: 'json',
        success: function (response) {
            if (response.unit_ids && response.ledger_ids && response.unit_names) {
                // Clear any existing options
                $('#add-to-account-unit2').empty();

                // Append a default "Please select" option with no value
                $('#add-to-account-unit2').append(
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

                    $('#add-to-account-unit2').append(
                        $('<option>', {
                            value: unit_id,  // Value is unit_id
                            'data-ledger-id': ledger_id  // Store ledger_id in data attribute
                        }).text(unit_name)  // Display text is unit_name
                    );
                });

                // Hide the loading spinner and show the dropdown
                $('#units-loader2').hide();
                $('#add-to-account-unit2').show();
            } else {
                console.error('Error: No units found in response.');
                $('#units-loader2').hide();
            }
        },
        error: function (xhr, status, error) {
            console.error('Error fetching units:', error);
            $('#units-loader2').hide();
        }
    });
}



$(document).ready(function () {
    // Set Australia/Brisbane timezone date as current date
    const currentDate = new Date().toLocaleString("en-US", { timeZone: "Australia/Brisbane" });
    const currentDateObj = new Date(currentDate);
    let selectedDate = null; // Variable to store the selected date
    
    // Initialize the inline date picker
    $('#inline-datepicker').datepicker({
        startDate: currentDateObj, // Prevent past dates
        todayHighlight: true,      // Highlight today's date
		format: "dd/mm/yyyy",      // Display format
        autoclose: true            // Close when date is selected
    }).on('changeDate', function (e) {
        selectedDate = new Date(e.date); // Update selected date
        validateDate(selectedDate); // Validate the date
    });

    // Function to validate if the selected date is at least 14 days from today
    function validateDate(selectedDate) {
        const diffInDays = Math.floor((selectedDate - currentDateObj) / (1000 * 60 * 60 * 24));

        if (diffInDays < 14) {
            Swal.fire({
                icon: 'error',
                title: 'Invalid Date',
                text: 'Mammoth Storage requires 14 days notice prior to vacating. Please select a date after 14 days from today.',
                confirmButtonText: 'Okay'
            });
            return false; // Return false if the date is invalid
        }
        return true; // Return true if the date is valid
    }

    // Function to format the date as yyyy-mm-dd without timezone conversion
    function formatDate(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0'); // Months are zero-based
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }

	// Convert API date string YYYY-MM-DD to display DD/MM/YYYY
	function apiToDisplay(dateStr) {
		if (typeof dateStr === 'string') {
			// Extract YYYY-MM-DD from start of string, supports 'YYYY-MM-DD', 'YYYY-MM-DDTHH:MM:SS', 'YYYY-MM-DD HH:MM:SS'
			var match = dateStr.match(/^(\d{4})-(\d{2})-(\d{2})/);
			if (match) {
				var y = match[1];
				var m = match[2];
				var d = match[3];
				return `${d}/${m}/${y}`;
			}
		}
		return dateStr;
	}

	// Convert display date string DD/MM/YYYY to API YYYY-MM-DD
		// Convert display date string DD/MM/YYYY to API YYYY-MM-DD
        function displayToApi(dateStr) {
            if (typeof dateStr === 'string' && /^\d{2}\/\d{2}\/\d{4}$/.test(dateStr)) {
                const [d, m, y] = dateStr.split('/');
                return `${y}-${m}-${d}`;
            }
            return dateStr;
        }
    
        // Helper: parse currency text like "$1,234.56" to 1234.56
        function parseCurrency(str) {
            if (!str) return 0;
            // Remove anything that's not digit or dot
            var cleaned = String(str).replace(/[^0-9.]/g, '');
            var num = parseFloat(cleaned);
            return isNaN(num) ? 0 : num;
        }

    	// NEW: Function to show rent calculation with your exact formula
	function showRentCalculation(rentData) {
		const rentDue = rentData.rent_due;
		const totalCurrentDue = rentData.total_current_due || 0;
		const totalOwing = rentData.total_owing || rentDue;
		const paidThrough = apiToDisplay(rentData.paid_through_date);
		const scheduledOut = apiToDisplay(rentData.scheduled_out_date);
		const daysBetween = rentData.days_between;
		const dailyRate = rentData.daily_rate;
		const monthlyRate = rentData.monthly_rate;
		const annualRate = rentData.annual_rate;

		Swal.fire({
			title: 'Rent Payment Required',
			html: `
				<div class="text-start">
					<h5>Rent Calculation Details:</h5>
					<div class="row mb-3">
						<div class="col-6">
							<p><strong>Paid Through Date:</strong><br>${paidThrough}</p>
							<p><strong>Scheduled Move Out:</strong><br>${scheduledOut}</p>
						</div>
						<div class="col-6">
							<p><strong>Days Owing:</strong><br>${daysBetween} days</p>
							<p><strong>Monthly Rental Rate:</strong><br>$${Number(monthlyRate).toFixed(2)}</p>
						</div>
					</div>
					<div class="card bg-light p-3 mb-3">
						<p class="mb-1"><strong>Rent Owing to Move Out:</strong> $${Number(rentDue).toFixed(2)}</p>
						<p class="mb-1"><strong>+ Total Current Due:</strong> $${Number(totalCurrentDue).toFixed(2)}</p> 
						<h4 class="text-primary mt-2"><strong>Total Owing: $${Number(totalOwing).toFixed(2)}</strong></h4>
					</div>
					<p class="text-muted">Payment is required before scheduling your move out.</p>
				</div>
			`,
			icon: 'info',
			showCancelButton: true,
			confirmButtonText: 'Proceed to Payment',
			cancelButtonText: 'Cancel',
			confirmButtonColor: '#D02729',
			cancelButtonColor: '#6c757d',
			width: '700px'
		}).then((result) => {
			if (result.isConfirmed) {
				// Open payment modal with combined total
				openMoveoutPaymentModal(totalOwing, rentData);
			}
		});
	}

        // NEW: Function to process payment with real payment gateway
        function processPayment(amount, rentData) {
            Swal.fire({
                title: 'Processing Payment',
                html: `
                    <div class="text-center">
                        <div class="spinner-border text-primary mb-3" role="status" style="width: 3rem; height: 3rem;">
                            <span class="visually-hidden">Processing...</span>
                        </div>
                        <p>Processing payment of <strong>$${amount}</strong>...</p>
                        <p class="text-muted">Please wait while we process your payment.</p>
                    </div>
                `,
                showConfirmButton: false,
                allowOutsideClick: false,
                timer: 3000
            }).then(() => {
                // Make actual payment API call
                makePaymentAPI(amount, rentData);
            });
        }

    // NEW: Function to make payment API call
        // NEW: Function to make payment API call
        function makePaymentAPI(amount, rentData) {
            // Gather form data for payment
            var paymentData = {
                ledgerID: $('#add-to-account-unit2').find(':selected').data('ledger-id'),
                amount: amount,
                dateScheduledOut: formatDate(selectedDate)
            };
    
            // AJAX call to process payment
            $.ajax({
                url: site_url + 'schedule/process-payment', // New payment endpoint
                type: 'POST',
                contentType: 'application/json',
                dataType: 'json',
                data: JSON.stringify(paymentData),
                success: function (response) {
                    if (response.success) {
                        Swal.fire({
                            title: 'Payment Successful!',
                            text: 'Your payment has been processed successfully. Your moveout has been scheduled.',
                            icon: 'success',
                            confirmButtonText: 'Great!',
                            timer: 5000,
                            timerProgressBar: true
                        });
                    } else {
                        Swal.fire({
                            title: 'Payment Failed',
                            text: 'Error: ' + response.error,
                            icon: 'error',
                            confirmButtonText: 'Try Again'
                        });
                    }
                },
                error: function (xhr, status, error) {
                    Swal.fire({
                        title: 'Payment Error',
                        text: 'Error processing payment: ' + error,
                        icon: 'error',
                        confirmButtonText: 'Try Again'
                    });
                }
            });
        }

    // Form submission with spinner and AJAX
    $('#schedule-moveout-form').on('submit', function (e) {
        e.preventDefault();

        // Clear existing messages
        $('#response-message4').removeClass('alert-success alert-danger').hide();

        // Validate form fields
        var ledgerID = $('#add-to-account-unit2').find(':selected').data('ledger-id');

        // Ensure ledgerID is valid
        if (!ledgerID || ledgerID === 'undefined') {
            $('#response-message4')
                .addClass('alert-danger')
                .text('Please select a valid unit to schedule your move out.')
                .show();
            return;
        }

        // Check if a valid date is selected
        if (!selectedDate || !validateDate(selectedDate)) {
            Swal.fire({
                icon: 'error',
                title: 'No Valid Date Selected',
                text: 'Please select a valid move out date that is at least 14 days from today.',
                confirmButtonText: 'Okay'
            });
            return; // Stop form submission if validation fails
        }

        // Show the form loader overlay
        $('#schedule-moveout-loader').show();

        // Gather form data, including the selected date and cancel flag
        var formData = {
            ledgerID: $('#add-to-account-unit2').find(':selected').data('ledger-id'),
            dateScheduledOut: formatDate(selectedDate), // Manually format date as yyyy-mm-dd
            dateScheduledOutWithCancel: false // Always false for new moveouts
        };

        // AJAX call to submit the form
        $.ajax({
            url: site_url + 'schedule/moveout', // Your controller function route
            type: 'POST',
            contentType: 'application/json',
            dataType: 'json',
            data: JSON.stringify(formData),
            success: function (response) {
                $('#schedule-moveout-loader').hide();
                $('#response-message4').removeClass('alert-success alert-danger').hide();

                // Handle response
                if (response.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: 'Your moveout has been successfully scheduled. You will receive a confirmation email shortly.',
                        confirmButtonText: 'Great!',
                        timer: 5000,
                        timerProgressBar: true
                    });
                } else if (response.requires_payment) {
                    // First make a test payment call to check if unit is complimentary
                    var testPaymentData = {
                        creditCardTypeID: 1,
                        creditCardNum: '0000000000000000',
                        creditCardExpire: '12/25',
                        creditCardHolderName: 'Test',
                        creditCardCVV: '000',
                        paymentOptions: 1,
                        sUnitIDs: String($('#add-to-account-unit2').val()),
                        sPaymentAmounts: '0.01'
                    };

                    $.ajax({
                        url: site_url + 'payment/make_payment',
                        type: 'POST',
                        contentType: 'application/json',
                        dataType: 'json',
                        data: JSON.stringify(testPaymentData),
                        success: function (testResponse) {
                            if (testResponse && !testResponse.success && 
                                (/no payment needed/i.test(testResponse.error) || /complimentary/i.test(testResponse.error))) {
                                // Unit is complimentary, schedule moveout directly
                                scheduleMoveoutDirectly();
                            } else {
                                // Unit requires payment, show rent calculation modal
                                showRentCalculation(response.rent_calculation);
                            }
                        },
                        error: function () {
                            // If test fails, show rent calculation modal as fallback
                            showRentCalculation(response.rent_calculation);
                        }
                    });
                } else {
                    $('#response-message4')
                        .addClass('alert-danger')
                        .text('Error: ' + response.error)
                        .show();
                }
            },
            error: function (xhr, status, error) {
                $('#schedule-moveout-loader').hide();
                $('#response-message4')
                    .addClass('alert-danger')
                    .text('Error submitting form: ' + error)
                    .show();
            }
        });
    });

    function submitForm(dateScheduledOutWithCancel) {
        // Show the form loader overlay
        $('#schedule-moveout-loader').show();

        // Gather form data, including the selected date and cancel flag
        var formData = {
            ledgerID: $('#add-to-account-unit2').find(':selected').data('ledger-id'),
            dateScheduledOut: formatDate(selectedDate), // Manually format date as yyyy-mm-dd
            dateScheduledOutWithCancel: dateScheduledOutWithCancel // Include cancel flag
        };

        var messageMoveOutText = "";

        if (dateScheduledOutWithCancel == true) {
            messageMoveOutText = "Your scheduled move out date has been successfully cancelled.";
        } else if (dateScheduledOutWithCancel == false) {
            messageMoveOutText = "Your scheduled move out date has been successfully created.";
        }

        // AJAX call to submit the form
        $.ajax({
            url: site_url + 'schedule/moveout', // Your controller function route
            type: 'POST',
            contentType: 'application/json',
            dataType: 'json',
            data: JSON.stringify(formData),
            success: function (response) {
                $('#schedule-moveout-loader').hide();
                $('#response-message4').removeClass('alert-success alert-danger').hide();

                // Handle response
                if (response.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: messageMoveOutText,
                        confirmButtonText: 'Great!',
                        timer: 5000, // Optional: auto-close after 5 seconds
                        timerProgressBar: true
                    });
                } else {
                    $('#response-message4')
                        .addClass('alert-danger')
                        .text('Error: ' + response.error)
                        .show();
                }
            },
            error: function (xhr, status, error) {
                $('#schedule-moveout-loader').hide();
                $('#response-message4')
                    .addClass('alert-danger')
                    .text('Error submitting form: ' + error)
                    .show();
            }
        });
    }

    // Attach once
    $(document).on('submit', '#moveout-payment-form', function (e) {
        e.preventDefault();

        $('#moveout-payment-message').removeClass('alert-success alert-danger').hide();

        // Basic validation
        var name = $('#mo-credit-card-holder').val();
        var number = $('#mo-credit-card-number').val();
        var month = $('#mo-credit-card-expiry-month').val();
        var year = $('#mo-credit-card-expiry-year').val();
        var cvv = $('#mo-credit-card-cvv').val();
        var amount = parseFloat($('#mo-payment-amount').val());

        // IMPORTANT: UnitID vs LedgerID
        var unitID = $('#add-to-account-unit2').val(); // UnitID comes from the select value
        var ledgerID = $('#mo-ledger-id').val();       // LedgerID is used only for scheduling moveout

        var dateScheduledOut = displayToApi($('#mo-scheduled-out').val());

        if (!name || !number || !month || !year || !cvv || !amount || !unitID || !ledgerID || !dateScheduledOut) {
            $('#moveout-payment-message').addClass('alert-danger').text('Please fill in all required fields.').show();
            return;
        }

        // Read the same card-type value that Make a Payment uses
        var selectedCreditCardOption = $('#credit-card-type').find(':selected');
        var paymentTypeData = selectedCreditCardOption.data('payment-type') || 1;

        // Show loader
        $('#moveout-payment-loader').show();

        // Build payload exactly like Make a Payment module, using UnitID(s)
        var formData = {
            creditCardTypeID: paymentTypeData,
            creditCardNum: number,
            creditCardExpire: year + '-' + month, // YYYY-MM (matches existing module)
            creditCardHolderName: name,
            creditCardCVV: cvv,
            paymentOptions: 1,
            sUnitIDs: String(unitID),              // <-- MUST be UnitID, not LedgerID
            sPaymentAmounts: amount.toFixed(2)
        };

        // First: real payment
        $.ajax({
            url: site_url + 'payment/make_payment',
            type: 'POST',
            contentType: 'application/json',
            dataType: 'json',
            data: JSON.stringify(formData),
            success: function (payResponse) {
                console.log("payResponse-----", payResponse);
                console.log("success-----", payResponse.success);
                if (payResponse && payResponse.success) {
                    // Hide modal loader and show progress popup
                    $('#moveout-payment-loader').hide();
                    Swal.fire({
                        icon: 'success',
                        title: 'Payment Successful',
                        html: '<div class="text-center"><p>Scheduling your move out now...</p><br><p>Please do not refresh or close this page while it\'s loading Your request is being processed. Thank you for your patience.</p><div class="spinner-border text-primary" role="status" style="width: 2.5rem; height: 2.5rem;"><span class="visually-hidden">Loading...</span></div></div>',
                        allowOutsideClick: false,
                        showConfirmButton: false
                    });
                    // Second: schedule moveout after 10s delay to allow payment to reflect in ledger
                    setTimeout(function () {
                        $.ajax({
                            url: site_url + 'schedule/moveout',
                            type: 'POST',
                            contentType: 'application/json',
                            dataType: 'json',
                            data: JSON.stringify({
                                ledgerID: ledgerID,
                                dateScheduledOut: dateScheduledOut,
                                dateScheduledOutWithCancel: false
                            }),
                            success: function (moResponse) {
                                // Close progress popup
                                Swal.close();

                                if (moResponse && moResponse.success) {
                                    var modalEl = document.getElementById('moveout-payment-modal');
                                    var modal = bootstrap.Modal.getInstance(modalEl);
                                    modal.hide();

                                    Swal.fire({
                                        icon: 'success',
                                        title: 'Payment Successful!',
                                        text: 'Your payment has been processed and your move out is scheduled.',
                                        confirmButtonText: 'Great!',
                                        timer: 5000,
                                        timerProgressBar: true
                                    });
                                } else {
                                    Swal.fire({
                                        icon: 'error',
                                        title: 'Scheduling Failed',
                                        text: (moResponse && moResponse.error ? moResponse.error : (moResponse && moResponse.requires_payment ? 'Payment still required. Please wait and try again.' : 'Unknown error')),
                                        confirmButtonText: 'OK'
                                    });
                                }
                            },
                            error: function (xhr, status, error) {
                                Swal.close();
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Scheduling Error',
                                    text: 'Move out scheduling error: ' + error,
                                    confirmButtonText: 'OK'
                                });
                            }
                        });
                    }, 10000);
                } 
                else {
                    var errorText = (payResponse && payResponse.error ? payResponse.error : 'Unknown error');

                    // If no payment is needed (complimentary unit), continue to schedule moveout
                    if (/no payment needed/i.test(errorText) || /complimentary/i.test(errorText)) {
                        $('#moveout-payment-loader').hide();
                        
                        // Show info message that no payment is needed
                        Swal.fire({
                            icon: 'info',
                            title: 'No Payment Required',
                            text: 'This unit is complimentary. Proceeding to schedule your move out...',
                            showConfirmButton: false,
                            timer: 2000
                        }).then(() => {
                            // Schedule moveout directly
                            $.ajax({
                                url: site_url + 'schedule/moveout',
                                type: 'POST',
                                contentType: 'application/json',
                                dataType: 'json',
                                data: JSON.stringify({
                                    ledgerID: ledgerID,
                                    dateScheduledOut: dateScheduledOut,
                                    dateScheduledOutWithCancel: false
                                }),
                                success: function (moResponse) {
                                    console.log("moResponse---", moResponse);
                                    
                                    if (moResponse && moResponse.success) {
                                        var modalEl = document.getElementById('moveout-payment-modal');
                                        var modal = bootstrap.Modal.getInstance(modalEl);
                                        modal.hide();

                                        Swal.fire({
                                            icon: 'success',
                                            title: 'Move Out Scheduled!',
                                            text: 'Your move out has been scheduled successfully. This unit is complimentary, so no payment was required.',
                                            confirmButtonText: 'Great!',
                                            timer: 5000,
                                            timerProgressBar: true
                                        });
                                    } else {
                                        Swal.fire({
                                            icon: 'error',
                                            title: 'Scheduling Failed',
                                            text: (moResponse && moResponse.error ? moResponse.error : 'Unknown error'),
                                            confirmButtonText: 'OK'
                                        });
                                    }
                                },
                                error: function (xhr, status, error) {
                                    Swal.fire({
                                        icon: 'error',
                                        title: 'Scheduling Error',
                                        text: 'Move out scheduling error: ' + error,
                                        confirmButtonText: 'OK'
                                    });
                                }
                            });
                        });
                        return;
                    }

                    $('#moveout-payment-loader').hide();
                    
                    // Check if error is about being too many days past due
                    if (/too many days past due/i.test(errorText) || /online payments disabled/i.test(errorText)) {
                        $('#moveout-payment-message').addClass('alert-danger').text('Your account is too far past due. Please contact the office to put in your notice to vacate.').show();
                    } else {
                        $('#moveout-payment-message').addClass('alert-danger').text('Payment failed: ' + errorText).show();
                    }
                }
            },
            error: function (xhr, status, error) {
                $('#moveout-payment-loader').hide();
                $('#moveout-payment-message').addClass('alert-danger').text('Payment error: ' + error).show();
            }
        });
    });

    function openMoveoutPaymentModal(amount, rentData) {
        // Prefill amount and linkage fields with proper formatting
        $('#mo-payment-amount').val(amount.toFixed(2));
        $('#mo-ledger-id').val($('#add-to-account-unit2').find(':selected').data('ledger-id'));
		$('#mo-scheduled-out').val(apiToDisplay(rentData.scheduled_out_date));

        // Clear messages
        $('#moveout-payment-message').removeClass('alert-success alert-danger').hide();

        // Show modal (Bootstrap 5)
        var modal = new bootstrap.Modal(document.getElementById('moveout-payment-modal'));
        modal.show();
    }

    function scheduleMoveoutDirectly() {
        var formData = {
            ledgerID: $('#add-to-account-unit2').find(':selected').data('ledger-id'),
            dateScheduledOut: formatDate(selectedDate),
            dateScheduledOutWithCancel: false
        };

        $.ajax({
            url: site_url + 'schedule/moveout',
            type: 'POST',
            contentType: 'application/json',
            dataType: 'json',
            data: JSON.stringify(formData),
            success: function (moResponse) {
                if (moResponse && moResponse.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Move Out Scheduled!',
                        text: 'Your move out has been scheduled successfully. This unit is complimentary, so no payment was required.',
                        confirmButtonText: 'Great!',
                        timer: 5000,
                        timerProgressBar: true
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Scheduling Failed',
                        text: (moResponse && moResponse.error ? moResponse.error : 'Unknown error'),
                        confirmButtonText: 'OK'
                    });
                }
            },
            error: function (xhr, status, error) {
                Swal.fire({
                    icon: 'error',
                    title: 'Scheduling Error',
                    text: 'Move out scheduling error: ' + error,
                    confirmButtonText: 'OK'
                });
            }
        });
    }
});
