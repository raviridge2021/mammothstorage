<?php
defined('BASEPATH') or exit('No direct script access allowed');

class InvoiceController extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
    }

    public function getInvoiceHistory()
    {
        // Get user session data
        $user_data = $this->session->userdata('user_data');

        // If user is not logged in, return an error
        if (!$user_data || !$user_data['logged_in']) {
            echo json_encode(['data' => [], 'error' => 'User not logged in.']);
            return;
        }

        // Decode the incoming JSON request
        $requestData = json_decode($this->input->raw_input_stream, true);

        // Extract DataTables parameters
        $draw = $requestData['draw'];
        $search_value = $requestData['search']['value'] ?? '';  // Search term
        $start = $requestData['start'] ?? 0;  // Pagination start
        $length = $requestData['length'] ?? 25;  // Pagination length
        $order_column = $requestData['order'][0]['column'];  // Column index for sorting
        $order_dir = $requestData['order'][0]['dir'];  // Order direction ('asc'/'desc')

        $tenant_id = $user_data['user_id'];
        $location = $user_data['location'];

        $currentDate = date('Y-m-d');
        $lastYearDate = date('Y-m-d', strtotime('-1 year'));

        // Fetch the invoices via a SOAP request
        $response = $this->fetchTenantInvoices($location, $tenant_id, $lastYearDate, $currentDate);

        if (!$response) {
            echo json_encode(['data' => [], 'error' => 'Failed to fetch invoices.']);
            return;
        }

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($response);

        if ($xml === false) {
            echo json_encode(['data' => [], 'error' => 'Failed to parse the invoice data.']);
            return;
        }

        $xml->registerXPathNamespace('soap', 'http://schemas.xmlsoap.org/soap/envelope/');
        $xml->registerXPathNamespace('diffgr', 'urn:schemas-microsoft-com:xml-diffgram-v1');

        // Handle errors
        $error_code = $xml->xpath('//diffgr:diffgram/NewDataSet/RT/Ret_Code');
        $error_msg = $xml->xpath('//diffgr:diffgram/NewDataSet/RT/Ret_Msg');

        if (!empty($error_code) && (int)$error_code[0] < 0) {
            echo json_encode(['success' => false, 'error' => (string)$error_msg[0]]);
            return;
        }

        // Extract invoice data
        $invoices = $xml->xpath('//diffgr:diffgram/NewDataSet/Table1');
        $invoice_data = [];

        foreach ($invoices as $invoice) {
            $invoice_num = (string) $invoice->iInvoiceNum;

            // If the invoice number already exists, group the units and sum the amounts
            if (isset($invoice_data[$invoice_num])) {
                $invoice_data[$invoice_num]['total_amount'] += (float) $invoice->dcAmt;

                // Add unit name if it hasn't been added yet
                if (!in_array((string) $invoice->sUnitName, $invoice_data[$invoice_num]['unit_numbers'])) {
                    $invoice_data[$invoice_num]['unit_numbers'][] = (string) $invoice->sUnitName;
                }
            } else {
                // Create a new invoice entry
                $invoice_data[$invoice_num] = [
                    'invoice_number' => $invoice_num,
                    'unit_numbers' => [(string) $invoice->sUnitName],  // Start unit as array
                    'invoice_date' => date('d-m-Y', strtotime((string) $invoice->dInvoiced)),
                    'due_date' => date('d-m-Y', strtotime((string) $invoice->dDue)),
                    'total_amount' => (float)$invoice->dcAmt,
                ];
            }
        }

        // Apply search filter
        if (!empty($search_value)) {
            $invoice_data = array_filter($invoice_data, function ($invoice) use ($search_value) {
                return stripos($invoice['invoice_number'], $search_value) !== false;
            });
        }

        // Column mapping for DataTables to actual array keys
        $columns = [
            0 => 'invoice_number',
            1 => 'unit_number',
            2 => 'invoice_date',
            3 => 'due_date',
            4 => 'total_amount'
        ];

        // Apply sorting
        usort($invoice_data, function ($a, $b) use ($columns, $order_column, $order_dir) {
            $column = $columns[$order_column];
            if ($order_dir === 'asc') {
                return strcmp($a[$column], $b[$column]);
            }
            return strcmp($b[$column], $a[$column]);
        });

        // Pagination: Extract the relevant slice of data
        $total_records = count($invoice_data);
        $invoice_data = array_slice($invoice_data, $start, $length);

        // After looping, apply 10% increase and rounding, and group units as a comma-separated string
        foreach ($invoice_data as &$invoice) {
            // Apply 10% increase and round to nearest 0.50
            $invoice['total_amount'] = number_format(round($invoice['total_amount'] * 1.10 * 2) / 2, 2);

            // Group units as a comma-separated string
            $invoice['unit_number'] = implode(', ', $invoice['unit_numbers']);

            // Remove 'unit_numbers' array since we now have 'unit_number' as a string
            unset($invoice['unit_numbers']);
        }

        // Return data in DataTables format
        echo json_encode([
            'draw' => intval($draw),
            'recordsTotal' => $total_records,
            'recordsFiltered' => $total_records,
            'data' => array_values($invoice_data)  // Re-indexed for DataTables
        ]);
    }


    // Function to fetch tenant invoices
    private function fetchTenantInvoices($location, $tenant_id, $lastYearDate, $currentDate)
    {
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://api.smdservers.net/CCWs_3.5/CallCenterWs.asmx',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => '
            <soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
              <soap:Body>
                <TenantInvoicesByTenantID xmlns="http://tempuri.org/CallCenterWs/CallCenterWs">
                  <sCorpCode>CCBZ</sCorpCode>
                  <sLocationCode>' . $location . '</sLocationCode>
                  <sCorpUserName>Ali:::MAMMOTHSW28BGD9OUBBX</sCorpUserName>
                  <sCorpPassword>Currie131!</sCorpPassword>
                  <dDateStart>' . $lastYearDate . '</dDateStart>
                  <dDateEnd>' . $currentDate . '</dDateEnd>
                  <sTenantIDsCommaDelimited>' . $tenant_id . '</sTenantIDsCommaDelimited>
                </TenantInvoicesByTenantID>
              </soap:Body>
            </soap:Envelope>',
            CURLOPT_HTTPHEADER => array(
                'SOAPAction: http://tempuri.org/CallCenterWs/CallCenterWs/TenantInvoicesByTenantID',
                'Content-Type: text/xml; charset=utf-8',
            ),
        ));

        $response = curl_exec($curl);
        curl_close($curl);
        return $response;
    }
}
