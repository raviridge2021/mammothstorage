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
    let dateScheduledOutWithCancel = false; // Default value
    // Initialize the inline date picker
    $('#inline-datepicker').datepicker({
        startDate: currentDateObj, // Prevent past dates
        todayHighlight: true,      // Highlight today's date
        format: "yyyy-mm-dd",      // Set date format
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


        Swal.fire({
            title: 'Choose Your Action',
            text: 'Do you want to schedule your move out date for 14 days in advance?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Move Out',
            cancelButtonText: 'Cancel Move Out',
            reverseButtons: true,
            showCloseButton: true, // Enable the default close button
            customClass: {
                confirmButton: 'btn-custom-primary', // Custom class for Moveout button
                cancelButton: 'btn-cancel',  // Custom class for Cancel button
                denyButton: 'btn-close',     // Custom class for Close button
            },
        }).then((result) => {
            if (result.isConfirmed) {
                // Moveout button clicked
                submitForm(false); // Pass false for dateScheduledOutWithCancel
            } else if (result.dismiss === Swal.DismissReason.cancel) {
                // Cancel button clicked
                submitForm(true); // Pass true for dateScheduledOutWithCancel
            }
        });

        /*
        // AJAX call to submit the form
        $.ajax({
            url: site_url + 'schedule/moveout',  // Your controller function route
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
                        text: 'Your move out has been successfully scheduled. You will receive a confirmation email shortly.',
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
        });*/
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
});
