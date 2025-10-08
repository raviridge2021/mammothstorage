// Variables to hold non-zero payment unit IDs and amounts
var sUnitIDs = [];
var sPaymentAmounts = [];
var numberOfFuturePeriodsPaymentAmount = 0;


$(document).ready(function () {

    // Show the loader and ensure the profile info and error message are hidden
    $('#profile-loader').show();

    // AJAX request to load tenant information when the profile page loads
    $.ajax({
        url: site_url + 'profile/get_tenant_info', // Use the appropriate URL
        type: 'GET',
        dataType: 'json',
        success: function (response) {
            // If the response is successful
            if (response.success) {
                var tenantInfo = response.tenant_info;

                // Hide the loader and show the profile info with a fade-in effect
                $('#profile-loader').fadeOut(300, function () {
                    $('#profile-info').fadeIn(300);
                });

                // Populate the data dynamically
                $('#tenant-name').text(tenantInfo.sFName + ' ' + tenantInfo.sLName);
                $('#tenant-email').attr('href', 'mailto:' + tenantInfo.sEmail).text(tenantInfo.sEmail);
                $('#tenant-address').text(`${tenantInfo.sAddr1}, ${tenantInfo.sCity}, ${tenantInfo.sRegion}, ${tenantInfo.sPostalCode}, ${tenantInfo.sCountry}`);
                $('#tenant-company').text(tenantInfo.sCompany || 'N/A');
                $('#tenant-access-code').text(tenantInfo.sAccessCode || 'N/A');
                //$('#tenant-unit').text(tenantInfo.tenantUnit || '1190');  // Adjust this based on additional data
                //$('#tenant-total-due').text(tenantInfo.totalDue ? `$${tenantInfo.totalDue}` : '$0.00');  // Adjust this based on additional data


                $('#contact-name').val(tenantInfo.sFName + ' ' + tenantInfo.sLName);
                $('#contact-email').val(tenantInfo.sEmail);
                // Hide each spinner after the data is loaded, except those with the class "current-due-date"
                $('.spinner-border').not('.current-due-date, .paid-thru-slider').remove();


                getDueBalance();
                getPaidThroughDate();



            } else {
                // In case of an error, show the error message
                $('#profile-loader').fadeOut(300, function () {
                    $('#profile-error').fadeIn(300);
                });
            }
        },
        error: function () {
            // Handle AJAX error
            $('#profile-loader').fadeOut(300, function () {
                $('#profile-error').fadeIn(300);
            });
        }
    });
});


function getDueBalance() {

    // Make an AJAX request to get the payment amount
    $.ajax({
        url: 'account/get_balance',  // Update with your PHP API endpoint
        type: 'POST',
        dataType: 'json',
        success: function (response) {

            if (response.unit_ids && response.payment) {
                // Sum up all the payments from the response and set the final amount
                var totalPayment = response.payment.reduce(function (total, amount) {
                    return total + parseFloat(amount);
                }, 0);
                $('#tenant-total-due').text("$" + totalPayment.toFixed(2) || '0.00');

                // Remove only the spinner-border elements that have the "current-due-date" class
                $('.spinner-border.current-due-date').remove();

            } else {
                $('#tenant-total-due').text('0.00');
            }
        },
        error: function (xhr, status, error) {

            $('#tenant-total-due').text('0.00');
        }
    });
}


function getPaidThroughDate() {
    $.ajax({
        url: 'units/get_units', // Update with your API endpoint
        type: 'POST',
        dataType: 'json',
        success: function (response) {
            const unitNames = response.unit_names;
            const paidThrus = response.paid_thrus;
            const $sliderContent = $('#slider-content');

            $sliderContent.empty(); // Clear any existing content

            if (unitNames.length === 0) {
                $sliderContent.html('<p class="text-warning">No units available.</p>');
                $('.spinner-border.paid-thru-slider').remove();
                return;
            }

            unitNames.forEach((unitName, index) => {
                const paidThru = paidThrus[index];
                const isActive = index === 0 ? 'active' : ''; // Set the first item as active
                const sliderItem = `
                <div class="carousel-item ${isActive}">
                    <div class="col-12">
                        <div class="card border-0 shadow-sm" style="background: #f9f9f9">
                            <div class="card-body">
                                <h5 class="text-center"><i class="bi bi-key"></i> Unit: ${unitName}</h5>
                                <p class="text-center">Paid Through: ${paidThru}</p>
                            </div>
                        </div>
                    </div>
                </div>
            `;
                $sliderContent.append(sliderItem);
            });

            $('.spinner-border.paid-thru-slider').remove();

            loadInvoiceHistory();
            loadPaymentTypes();
        },
        error: function (xhr, status, error) {
            console.error('Error fetching data:', error);
            $('#slider-content').html('<div class="text-danger">Failed to load units. Please try again later.</div>');
        }
    });
}
