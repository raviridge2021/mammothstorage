$(document).ready(function () {


    function hideAccountForms() {


        $('#loginForm').hide(); // Show login form
        $('#signUpForm').hide(); // Show login form
        $('#forgotPasswordForm').hide(); // Show login form
    }


    function showLoginAccountForms() {

        // Change text back for Login
        $('.account-form-title-text').text('Welcome Back!');
        $('.account-form-text').text('Please login to your account');
        $('#loginForm').show(); // Show login form
    }

    hideAccountForms();
    showLoginAccountForms();


    // Show Sign Up form
    $('#showSignUp').click(function (event) {
        event.preventDefault();

        hideAccountForms();
        // Change text for Sign Up
        $('.account-form-title-text').text('Sign Up!');
        $('.account-form-text').text('Please complete your registration using a verified email address');

        $('#signUpForm').show(); // Show sign-up form
    });

    // Show Forgot Password form
    $('#showForgotPassword').click(function (event) {
        event.preventDefault();

        hideAccountForms();
        // Change text for Forgot Password
        $('.account-form-title-text').text('Forgot Password!');
        $('.account-form-text').text('Enter your email to reset your password');

        $('#forgotPasswordForm').show(); // Show forgot password form
    });

    // Show Login form
    $('#showLogin').click(function (event) {
        event.preventDefault();

        hideAccountForms();
        showLoginAccountForms();
    });


    // Handle form submission
    $('#signUpForm').on('submit', function (e) {
        e.preventDefault(); // Prevent the form from submitting the traditional way


        // Clear existing messages
        $('#signup-error-message').removeClass('alert-success alert-danger').hide();

        // Collect form data
        var emailAddress = $('#signUpEmail').val();
        var siteLocation = $('#site-location-2').val();
        var siteLocationText = $('#site-location-2 option:selected').text(); // Get the selected text


        // Validate form fields (optional)
        if (!emailAddress) {
            $('#signup-error-message')
                .removeClass('alert-success') // Remove success class if it exists
                .addClass('alert-danger') // Add danger class for error indication
                .text('Please enter your email address.')
                .show();
            return; // Stop form submission if validation fails
        }

        $('#spinner-overlay').fadeIn();

        // Send the AJAX request
        $.ajax({
            url: 'email/signup', // Replace this with your actual route (e.g., 'email/sendAdminEmail')
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                emailAddress: emailAddress,
                location: siteLocation,
                locationName: siteLocationText
            }),
            success: function (response) {

                // Hide the spinner
                $('#spinner-overlay').fadeOut();

                // Clear existing messages
                $('#signup-error-message').removeClass('alert-success alert-danger').hide();

                if (response.success) {
                    $('#signUpEmail').val(''); // Clear the email input

                    $('#signup-error-message')
                        .removeClass('alert-danger') // Remove danger class if it exists
                        .addClass('alert-success') // Add success class
                        .html('Thank you for signing up! Your account has been created successfully. We will send you a confirmation email shortly.')
                        .show();


                    // Redirect to the login page after a short delay (e.g., 2 seconds)
                    setTimeout(function () {
                    }, 1000); // 1000 milliseconds = 1 seconds

                    $('#spinner-overlay').fadeOut();



                    // Redirect to the login page after a short delay (e.g., 2 seconds)
                    setTimeout(function () {
                        $('#spinner-overlay').fadeIn();
                        $('.forms-container').hide(); // Show login form
                        hideAccountForms();
                        // window.location.href = 'https://account.mammothstorage.com.au/login';
                        $('#signupCard').removeClass('d-none').hide().fadeIn();
                        $('#spinner-overlay').fadeOut();
                    }, 1000); // 1000 milliseconds = 1 seconds



                } else {

                    // Hide the spinner
                    $('#spinner-overlay').fadeOut();


                    $('#signup-error-message')
                        .removeClass('alert-success') // Remove success class if it exists
                        .addClass('alert-danger') // Add danger class
                        .html('There was an issue creating your account. Please try again later, or contact us directly at customer support for assistance.')
                        .show();
                }


            },
            error: function (xhr, status, error) {
                // Hide the form loader overlay on error
                $('#spinner-overlay').fadeOut();

                $('#signup-error-message')
                    .removeClass('alert-success') // Ensure success class is removed if it exists
                    .addClass('alert-danger') // Add danger class for error indication
                    .html('We encountered a problem while trying to send your message. Please check your details and try again. If the issue persists, feel free to reach out to us at customer support for assistance.')
                    .show();
            }
        });
    });

    // Handle forgot password form submission
    $('#forgotPasswordForm').on('submit', function (e) {
        e.preventDefault(); // Prevent the form from submitting the traditional way

        // Clear existing messages
        $('#forgot-password-error-message').removeClass('alert-success alert-danger').hide();

        // Collect form data
        var emailAddress = $('#forgotEmail').val();
        var siteLocation = $('#site-location-3').val();
        var siteLocationText = $('#site-location-3 option:selected').text(); // Get the selected text

        // Validate form fields (optional)
        if (!emailAddress) {
            $('#forgot-password-error-message')
                .removeClass('alert-success') // Remove success class if it exists
                .addClass('alert-danger') // Add danger class for error indication
                .text('Please enter your email address to reset your password.')
                .show();
            return; // Stop form submission if validation fails
        }

        $('#spinner-overlay').fadeIn(); // Show spinner during the process

        // Send the AJAX request
        $.ajax({
            url: 'email/forgot_password', // Replace this with your actual route
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                emailAddress: emailAddress,
                location: siteLocation,
                locationName: siteLocationText
            }),
            success: function (response) {

                // Clear existing messages
                $('#forgot-password-error-message').removeClass('alert-success alert-danger').hide();

                if (response.success) {
                    $('#forgotEmail').val(''); // Clear the email input

                    $('#forgot-password-error-message')
                        .removeClass('alert-danger') // Remove danger class if it exists
                        .addClass('alert-success') // Add success class
                        .html('A password reset link has been sent to your email address. Please check your inbox.')
                        .show();

                    // Redirect to the login page after a short delay (e.g., 2 seconds)
                    setTimeout(function () {
                    }, 1000); // 1000 milliseconds = 1 seconds

                    $('#spinner-overlay').fadeOut();


                    // Redirect to the login page after a short delay (e.g., 2 seconds)
                    setTimeout(function () {
                        $('#spinner-overlay').fadeIn();
                        window.location.href = 'https://account.mammothstorage.com.au/login';
                    }, 1000); // 1000 milliseconds = 1 seconds


                } else {

                    // Hide the spinner
                    $('#spinner-overlay').fadeOut();


                    $('#forgot-password-error-message')
                        .removeClass('alert-success') // Remove success class if it exists
                        .addClass('alert-danger') // Add danger class
                        .html('There was an issue processing your request. Please try again later, or contact us directly at customer support for assistance.')
                        .show();
                }
            },
            error: function (xhr, status, error) {
                // Hide the form loader overlay on error
                $('#spinner-overlay').fadeOut();

                $('#forgot-password-error-message')
                    .removeClass('alert-success') // Ensure success class is removed if it exists
                    .addClass('alert-danger') // Add danger class for error indication
                    .html('We encountered a problem while trying to send the reset email. Please check your details and try again. If the issue persists, feel free to reach out to us at customer support for assistance.')
                    .show();
            }
        });
    });


});