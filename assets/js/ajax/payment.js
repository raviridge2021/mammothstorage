// Function to load Payment History
function loadPaymentHistory() {
    // Check if DataTable is already initialized
    if (!$.fn.DataTable.isDataTable('#payment-table')) {
        $('#payment-table').DataTable({
            processing: true,  // Show processing indicator
            serverSide: true,  // Enable server-side processing
            ajax: {
                url: site_url + 'payment/get_payments',  // The API URL for fetching payment data
                type: 'POST',  // Use POST to fetch data
                dataType: 'json',
                contentType: 'application/json',
                data: function (d) {
                    // Pass the DataTables request as JSON to the server
                    return JSON.stringify(d);
                },
                dataSrc: 'data'  // DataTables expects 'data' as the key in the response
            },
            columns: [
                { data: 'receipt_id' },  // Payment Date
                { data: 'unit_name' },   // Unit associated with the payment
                { data: 'payment_date' },  // Payment Date
                { data: 'description' },   // Payment Description
                {
                    data: 'payment_amount',
                    render: function (data) {
                        return '$' + parseFloat(data).toFixed(2);  // Format payment amount with a dollar sign
                    }
                }  // Payment Amount
            ],
            order: [[0, 'desc']],  // Default sort by payment date (descending)
            language: {
                emptyTable: "No payment history available",
                processing: "<div class='spinner-border text-primary'></div> Loading..."
            },
            responsive: true,  // Enable responsive table behavior
            pageLength: 25,  // Default show 25 records per page
            lengthMenu: [10, 25, 50, 75, 100],  // Options for the user to select records per page
        });
    } else {
        // If already initialized, refresh the table
        $('#payment-table').DataTable().ajax.reload();
    }
}

