function loadInvoiceHistory() {
    // Check if the DataTable is already initialized
    if (!$.fn.DataTable.isDataTable('#invoice-table')) {
        $('#invoice-table').DataTable({
            processing: true,  // Show processing indicator
            serverSide: true,   // Enable server-side processing
            pageLength: 25,  // Default to showing 25 rows per page
            ajax: {
                url: site_url + 'invoice/get_invoices',  // The API URL for fetching invoice data
                type: 'POST',  // Use POST request
                contentType: 'application/json',  // Set content type to JSON
                data: function (d) {
                    // Convert DataTables parameters to JSON format
                    return JSON.stringify(d);
                },
                dataSrc: 'data'  // DataTables expects 'data' as the key in the response
            },
            columns: [
                { data: 'invoice_number' },   // Invoice number
                { data: 'unit_number' },      // Units associated with the invoice
                { data: 'invoice_date' },     // Invoiced date
                { data: 'due_date' },         // Due date
                {
                    data: 'total_amount',      // Total amount
                    render: function (data, type, row) {
                        return '$' + data;    // Add dollar sign before the total amount
                    }
                }
            ],
            order: [[0, 'desc']],  // Default sort by the first column (Invoice #) in descending order
            language: {
                emptyTable: "No invoices available",
                processing: "<div class='spinner-border text-primary'></div> Loading..."
            },
            responsive: true,  // Enable responsive table behavior
            pageLength: 25,  // Default show 25 records per page
            lengthMenu: [10, 25, 50, 75, 100],  // Options for the user to select records per page
        });
    } else {
        // If already initialized, refresh the table
        $('#invoice-table').DataTable().ajax.reload();
    }
}
