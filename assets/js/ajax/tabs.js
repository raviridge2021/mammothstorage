$(document).ready(function () {
    // Listen for tab switching
    $('#myTab button[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
        var targetTab = $(e.target).attr("id"); // Get the clicked tab's ID

        console.log("Switched to tab: " + targetTab); // Debugging
        clearAlertMessages();

        // Call appropriate functions based on the clicked tab
        switch (targetTab) {
            case 'invoice-history-tab':
                loadInvoiceHistory(); // Trigger the invoice loading function
                break;
            case 'payment-history-tab':
                loadPaymentHistory(); // Trigger the payment loading function
                break;
            case 'make-payment-tab':
                break;
            case 'auto-payment-tab':
                loadAutoPayment(); // Trigger the payment loading function
                break;
            case 'schedule-moveout-tab':
                loadAllUnits(); // Trigger the payment loading function
                break;
            case 'contact-us-tab':
                // Call a function to handle the "Contact Us" tab
                break;
        }
    });
});
