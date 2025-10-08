
$(document).ready(function () {
    // Handle form submission
    $('#contact-form').on('submit', function (e) {
        e.preventDefault(); // Prevent the form from submitting the traditional way

        // Clear existing messages
        $('#response-message3').removeClass('alert-success alert-danger').hide();

        // Collect form data
        var customerName = $('#contact-name').val();
        var emailAddress = $('#contact-email').val();
        var messageText = $('#contact-message').val();

        // Validate form fields (optional)
        if (!customerName || !emailAddress || !messageText) {
            $('#response-message3')
                .addClass('alert-danger')
                .text('All fields are required. Please fill out all the information.')
                .show();
            return; // Stop form submission if validation fails
        }

        // Show the form loader overlay only for the auto-payment form
        $('#contact-form-loader').show();

        // Send the AJAX request
        $.ajax({
            url: 'email/send', // Replace this with your actual route (e.g., 'email/sendAdminEmail')
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                customerName: customerName,
                emailAddress: emailAddress,
                messageText: messageText
            }),
            success: function (response) {

                // Hide the form loader overlay after the response
                $('#contact-form-loader').hide();

                // Clear existing messages
                $('#response-message3').removeClass('alert-success alert-danger').hide();

                if (response.success) {
                    // Clear the form fields after a successful send
                    $('#contact-name').val('');
                    $('#contact-email').val('');
                    $('#contact-message').val('');

                    $('#response-message3')
                        .addClass('alert-success')
                        .text('Thank you for reaching out! Your message has been successfully sent. We will get back to you as soon as possible.')
                        .show();

                } else {
                    $('#response-message3')
                        .addClass('alert-danger')
                        .text('Sorry, there was an issue sending your message. Please try again later, or contact us directly at our email address.')
                        .show();
                }

            },
            error: function (xhr, status, error) {
                // Hide the form loader overlay on error
                $('#contact-form-loader').hide();

                $('#response-message3')
                    .addClass('alert-danger')
                    .text('Sorry, there was an issue sending your message. Please try again later, or contact us directly at our email address.')
                    .show();
            }
        });
    });
});



