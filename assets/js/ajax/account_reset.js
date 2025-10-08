$(document).ready(function () {

    // Show the loading spinner
    $('#spinner-overlay').fadeIn();

    // Redirect to the login page after a short delay (e.g., 2 seconds)
    setTimeout(function () {
    }, 3000); // 3000 milliseconds = 3 seconds

    $('#spinner-overlay').fadeOut();


    $('#resetPasswordForm').on('submit', function (e) {
        e.preventDefault(); // Prevent the default form submission

        // Clear previous error messages
        $('#reset-password-error-message').hide().removeClass('alert-danger alert-success');

        // Get form data
        var siteLocation = $('#location').val();
        var email = $('#resetPasswordEmail').val();
        var newPassword = $('#new-password').val();
        var confirmPassword = $('#confirm-password').val();


        // Password validation
        var passwordRequirements = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*()_+<>?])[A-Za-z\d!@#$%^&*()_+<>?]{8,}$/;

        if (!passwordRequirements.test(newPassword)) {
            $('#reset-password-error-message')
                .addClass('alert-danger')
                .text('Password must be at least 8 characters long and include at least one uppercase letter, one lowercase letter, one number, and one special character.')
                .show();
            return; // Stop form submission
        }

        // Password validation
        if (newPassword.length < 8) {
            $('#reset-password-error-message')
                .addClass('alert-danger')
                .text('Password must be at least 8 characters long.')
                .show();
            return; // Stop form submission
        }

        if (newPassword !== confirmPassword) {
            $('#reset-password-error-message')
                .addClass('alert-danger')
                .text('Passwords do not match. Please try again.')
                .show();
            return; // Stop form submission
        }

        // Show the loading spinner
        $('#spinner-overlay').fadeIn();

        // Prepare data to be sent
        var formData = {
            location: siteLocation,
            email: email,
            new_password: newPassword
        };

        // Send AJAX request
        $.ajax({
            url: site_url + 'reset/update_Password', // Replace with your actual endpoint
            type: 'POST',
            dataType: 'json',
            contentType: 'application/json',
            data: JSON.stringify(formData),
            success: function (response) {
                // Hide the spinner
                $('#spinner-overlay').fadeOut();

                // Clear existing messages
                $('#reset-password-error-message').hide();

                if (response.success) {
                    $('#reset-password-error-message')
                        .removeClass('alert-danger') // Remove danger class if it exists
                        .addClass('alert-success')   // Add success class
                        .html('Your password has been reset successfully. <a href="https://account.mammothstorage.com.au/login" class="ms-2 text-decoration-underline">Click here to login</a>')
                        .show();

                } else {
                    $('#reset-password-error-message')
                        .removeClass('alert-success') // Remove success class if it exists
                        .addClass('alert-danger') // Add danger class
                        .text(response.error) // Show the error message returned from server
                        .show();
                }
            },
            error: function (xhr, status, error) {
                // Hide the spinner
                $('#spinner-overlay').fadeOut();

                $('#reset-password-error-message')
                    .removeClass('alert-success') // Ensure success class is removed if it exists
                    .addClass('alert-danger') // Add danger class for error indication
                    .text('An error occurred while resetting your password. Please try again later.')
                    .show();
            }
        });
    });
});
