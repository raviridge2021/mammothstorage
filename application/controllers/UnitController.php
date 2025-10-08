<?php
defined('BASEPATH') or exit('No direct script access allowed');

class UnitController extends CI_Controller
{

    // Function to display the form view
    public function index()
    {
        $this->load->view('units_view');
    }

    // Function to handle the API request
    public function fetchUnits()
    {
        // Parse the JSON input from the request body
        $inputData = json_decode(file_get_contents('php://input'), true);

        // Check if locationCode is present in the request
        if (!isset($inputData['locationCode']) || empty($inputData['locationCode'])) {
            echo json_encode(['error' => true, 'message' => 'Missing or invalid locationCode']);
            return;
        }

        $locationCode = htmlspecialchars($inputData['locationCode']); // Sanitize input

        // Prepare the SOAP request XML with the provided locationCode
        $soapRequest = <<<XML
    <soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
        <soap:Body>
            <UnitsInformation xmlns="http://tempuri.org/CallCenterWs/CallCenterWs">
                <sCorpCode>CCBZ</sCorpCode>
                <sLocationCode>{$locationCode}</sLocationCode>
                <sCorpUserName>Ali:::MAMMOTHSW28BGD9OUBBX</sCorpUserName>
                <sCorpPassword>Currie131!</sCorpPassword>
            </UnitsInformation>
        </soap:Body>
    </soap:Envelope>
    XML;

        // Initialize cURL
        $curl = curl_init();

        // Set cURL options
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://api.smdservers.net/CCWs_3.5/CallCenterWs.asmx',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $soapRequest,
            CURLOPT_HTTPHEADER => array(
                'Content-Type: text/xml; charset=utf-8',
                'SOAPAction: http://tempuri.org/CallCenterWs/CallCenterWs/UnitsInformation'
            ),
        ));

        // Execute the cURL request and get the response
        $response = curl_exec($curl);

        // Check for cURL errors
        if ($response === false) {
            echo json_encode(['error' => true, 'message' => curl_error($curl)]);
            curl_close($curl);
            return;
        }

        // Close cURL session
        curl_close($curl);

        // Parse the SOAP XML response
        $units = $this->parseSOAPResponse($response);

        // Check if there was an error in parsing the SOAP response
        if (isset($units['error']) && $units['error'] === true) {
            echo json_encode(['error' => true, 'message' => $units['message']]);
            return;
        }

        // Return the parsed units as JSON
        echo json_encode(['error' => false, 'data' => $units['data']]);
    }


    // Function to parse the SOAP response and extract units information
    private function parseSOAPResponse($response)
    {
        // Set the timezone
        date_default_timezone_set('Australia/Brisbane');

        // Suppress libxml errors and allow to fetch them manually
        libxml_use_internal_errors(true);

        // Load the SOAP response as SimpleXML
        $xml = simplexml_load_string($response);

        // Handle XML parsing errors
        if ($xml === false) {
            return ["error" => true, "message" => "Failed to parse SOAP response"];
        }

        // Register namespaces for correct XPath queries
        $xml->registerXPathNamespace('soap', 'http://schemas.xmlsoap.org/soap/envelope/');
        $xml->registerXPathNamespace('diffgr', 'urn:schemas-microsoft-com:xml-diffgram-v1');

        // Check for any returned error codes and messages
        $errorCode = $xml->xpath('//RT/Ret_Code');
        $errorMessage = $xml->xpath('//RT/Ret_Msg');

        // If error codes are present, handle them
        if (!empty($errorCode) && !empty($errorMessage)) {
            $code = (string)$errorCode[0];
            $msg = (string)$errorMessage[0];

            // Map error codes to meaningful messages
            $errors = [
                '-95' => "Invalid Unit ID",
                '-98' => "Login failed",
                '-89' => "Invalid API License Key",
                '-99' => "General Exception: $msg"
            ];

            // If an error code is found, return the respective message
            $error = $errors[$code] ?? "Unknown error: $msg";
            return ["error" => true, "message" => $error];
        }

        // Extract units information from the SOAP response
        $result = $xml->xpath('//diffgr:diffgram/NewDataSet/Table');
        $units = [];

        // Loop through the units and extract relevant information
        if (!empty($result)) {
            foreach ($result as $unit) {
                $units[] = [
                    'UnitID' => (string) $unit->UnitID,
                    'dcWidth' => (float) $unit->dcWidth,
                    'dcLength' => (float) $unit->dcLength,
                    'sUnitName' => (string) $unit->sUnitName,
                    'dcStdRate' => (float) $unit->dcStdRate,
                    'bRented' => (bool) $unit->bRented,
                    'bClimate' => (bool) $unit->bClimate,
                    'bInside' => (bool) $unit->bInside
                ];
            }

            // Return extracted units data
            return ["error" => false, "data" => $units];
        } else {
            // If no unit information is found, return an error
            return ["error" => true, "message" => "No unit information found"];
        }
    }



    public function getAllUnits()
    {
        // Get user session data
        $user_data = $this->session->userdata('user_data');

        // If the user is not logged in, return an error
        if (!$user_data || !$user_data['logged_in']) {
            $this->output->set_content_type('application/json')->set_output(json_encode([
                'data' => [],
                'error' => 'User not logged in.'
            ]));
            return;
        }

        // Extract tenant_id and location from session data
        $tenant_id = $user_data['user_id'];
        $location = $user_data['location'];

        // Fetch account balance via SOAP request
        $response = $this->fetchAllAccountUnits($location, $tenant_id);

        if (!$response) {
            $this->output->set_content_type('application/json')->set_output(json_encode([
                'data' => [],
                'error' => 'Failed to fetch account balance.'
            ]));
            return;
        }

        // Handle XML parsing errors
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($response);

        if ($xml === false) {
            $this->output->set_content_type('application/json')->set_output(json_encode([
                'data' => [],
                'error' => 'Failed to parse the account balance data.'
            ]));
            return;
        }

        $xml->registerXPathNamespace('soap', 'http://schemas.xmlsoap.org/soap/envelope/');
        $xml->registerXPathNamespace('diffgr', 'urn:schemas-microsoft-com:xml-diffgram-v1');

        // Handle errors
        $error_code = $xml->xpath('//diffgr:diffgram/NewDataSet/RT/Ret_Code');
        $error_msg = $xml->xpath('//diffgr:diffgram/NewDataSet/RT/Ret_Msg');

        if (!empty($error_code) && (int)$error_code[0] < 0) {
            $this->output->set_content_type('application/json')->set_output(json_encode([
                'success' => false,
                'error' => (string)$error_msg[0]
            ]));
            return;
        }

        // Extract balance details and handle errors
        $units = $xml->xpath('//diffgr:diffgram/NewDataSet/Table1');
        $unit_data = [];

        foreach ($units as $balance) {
            $unit_id = (int)$balance->UnitID;
            $ledger_id = (int)$balance->LedgerID;
            $unit_name = (string)$balance->sUnitName; // Extract unit name

            // If UnitID already exists, sum up the balance
            if (isset($unit_data[$unit_id])) {
            } else {
                // Create a new entry for each unique UnitID
                $unit_data[$unit_id] = [
                    'unit_id' => $unit_id,
                    'ledger_id' => $ledger_id,
                    'unit_name' => $unit_name,
                    'paid_thru' => $this->getPaidThroughDateByLedgerID($location, $ledger_id),
                ];
            }
        }

        // Prepare arrays for JSON response
        $unit_ids = [];
        $ledger_ids = [];
        $unit_names = [];
        $paid_thrus = [];

        foreach ($unit_data as $data) {
            $unit_ids[] = $data['unit_id'];
            $ledger_ids[] = $data['ledger_id'];
            $unit_names[] = $data['unit_name']; // Add unit names to a separate array
            $paid_thrus[] = $data['paid_thru'];
        }

        // Return the account balance data in the required format
        $this->output->set_content_type('application/json')->set_output(json_encode([
            'unit_ids' => $unit_ids,
            'ledger_ids' => $ledger_ids,
            'unit_names' => $unit_names,
            'paid_thrus' => $paid_thrus
        ]));
    }

    // Function to fetch account balance
    private function fetchAllAccountUnits($location, $tenant_id)
    {
        $curl = curl_init();

        curl_setopt_array($curl, [
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
                <CustomerAccountsBalanceDetails xmlns="http://tempuri.org/CallCenterWs/CallCenterWs">
                  <sCorpCode>CCBZ</sCorpCode>
                  <sLocationCode>' . $location . '</sLocationCode>
                  <sCorpUserName>Ali:::MAMMOTHSW28BGD9OUBBX</sCorpUserName>
                  <sCorpPassword>Currie131!</sCorpPassword>
                  <iTenantID>' . $tenant_id . '</iTenantID>
                </CustomerAccountsBalanceDetails>
              </soap:Body>
            </soap:Envelope>',
            CURLOPT_HTTPHEADER => [
                'SOAPAction: http://tempuri.org/CallCenterWs/CallCenterWs/CustomerAccountsBalanceDetails',
                'Content-Type: text/xml; charset=utf-8',
            ],
        ]);

        $response = curl_exec($curl);
        curl_close($curl);
        return $response;
    }



    private function parsePaidThroughDate($response)
    {

        if (!$response) {
            $this->output->set_content_type('application/json')->set_output(json_encode([
                'data' => [],
                'error' => 'Failed to fetch paid through date.'
            ]));
            return;
        }

        // Handle XML parsing errors
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($response);

        if ($xml === false) {
            $this->output->set_content_type('application/json')->set_output(json_encode([
                'data' => [],
                'error' => 'Failed to parse the unit paid thru data.'
            ]));
            return;
        }

        $xml->registerXPathNamespace('soap', 'http://schemas.xmlsoap.org/soap/envelope/');
        $xml->registerXPathNamespace('diffgr', 'urn:schemas-microsoft-com:xml-diffgram-v1');

        // Handle errors
        $error_code = $xml->xpath('//diffgr:diffgram/NewDataSet/RT/Ret_Code');
        $error_msg = $xml->xpath('//diffgr:diffgram/NewDataSet/RT/Ret_Msg');

        if (!empty($error_code) && (int)$error_code[0] < 0) {
            $this->output->set_content_type('application/json')->set_output(json_encode([
                'success' => false,
                'error' => (string)$error_msg[0]
            ]));
            return;
        }

        // Extract balance details and handle errors
        $ledgers = $xml->xpath('//diffgr:diffgram/NewDataSet/Table');
        $paidThru = "";

        foreach ($ledgers as $ledger) {
            $timestamp = (string)$ledger->dPaidThru;
            $date = (new DateTime($timestamp))->format('d/m/Y'); // Extracts and formats the date as DD/MM/YYYY
            $paidThru = $date;
        }

        return $paidThru;
    }
    private function getPaidThroughDateByLedgerID($location, $ledgerID)
    {
        $curl = curl_init();

        curl_setopt_array($curl, [
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
                <PaidThroughDateByLedgerID xmlns="http://tempuri.org/CallCenterWs/CallCenterWs">
                  <sCorpCode>CCBZ</sCorpCode>
                  <sLocationCode>' . $location . '</sLocationCode>
                  <sCorpUserName>Ali:::MAMMOTHSW28BGD9OUBBX</sCorpUserName>
                  <sCorpPassword>Currie131!</sCorpPassword>
                  <iLedgerID>' . $ledgerID . '</iLedgerID>
                </PaidThroughDateByLedgerID>
              </soap:Body>
            </soap:Envelope>',
            CURLOPT_HTTPHEADER => [
                'SOAPAction: http://tempuri.org/CallCenterWs/CallCenterWs/PaidThroughDateByLedgerID',
                'Content-Type: text/xml; charset=utf-8',
            ],
        ]);

        $response = curl_exec($curl);
        curl_close($curl);

        return $this->parsePaidThroughDate($response);
    }
}
