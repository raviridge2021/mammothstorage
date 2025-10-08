<?php
defined('BASEPATH') or exit('No direct script access allowed');

class CustomerAccountsController extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
    }

    public function getFutureCharges()
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

        // Decode the incoming JSON request
        $requestData = json_decode($this->input->raw_input_stream, true);

        // Validate required fields
        if (!isset($requestData['numberOfFuturePeriods'])) {
            echo json_encode(['success' => false, 'error' => 'All required fields must be provided.']);
            return;
        }

        // Extract tenant_id and location from session data
        $tenant_id = $user_data['user_id'];
        $location = $user_data['location'];

        // Specify the number of future periods
        $number_of_future_periods = $requestData['numberOfFuturePeriods'];

        // Make the SOAP request to create future charges
        $response = $this->getFutureAllUnitsCharges($location, $tenant_id, $number_of_future_periods);

        if (!$response) {
            $this->output->set_content_type('application/json')->set_output(json_encode([
                'data' => [],
                'error' => 'Failed to make future charges.'
            ]));
            return;
        }

        // Handle XML parsing errors
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($response);

        if ($xml === false) {
            $this->output->set_content_type('application/json')->set_output(json_encode([
                'data' => [],
                'error' => 'Failed to parse the future charges data.'
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
        $balances = $xml->xpath('//diffgr:diffgram/NewDataSet/Table1');
        $balance_data = [];


        foreach ($balances as $balance) {
            $unit_id = (int)$balance->UnitID;
            $unit_name = (string)$balance->sUnitName; // Extract unit name
            $balance_amount = (float)$balance->dcBalance;

            // Check for sItem being "Rent"
            if ((string)$balance->sItem === 'Rent') {
                // If UnitID already exists, sum up the balance
                if (isset($balance_data[$unit_id])) {
                    $balance_data[$unit_id]['balance'] += $balance_amount;
                } else {
                    // Create a new entry for each unique UnitID
                    $balance_data[$unit_id] = [
                        'unit_id' => $unit_id,
                        'unit_name' => $unit_name,  // Include unit name
                        'balance' => $balance_amount
                    ];
                }
            }
        }
        // Prepare arrays for JSON response
        $unit_ids = [];
        $unit_names = [];
        $payments = [];


        foreach ($balance_data as $data) {
            $unit_ids[] = $data['unit_id'];
            $unit_names[] = $data['unit_name']; // Add unit names to a separate array
            $payments[] = number_format($data['balance'], 2); // Format balance to 2 decimal places
        }

        // Return the account balance data in the required format
        $this->output->set_content_type('application/json')->set_output(json_encode([
            'unit_ids' => $unit_ids,
            'unit_names' => $unit_names,
            'payment' => array_map(function ($p) {
                return round((float)str_replace(',', '', $p), 2);
            }, $payments)
        ]));
    }


    // Function to fetch create future charges
    private function getFutureAllUnitsCharges($location, $tenant_id, $number_of_future_periods)
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
                    <CustomerAccountsBalanceDetailsWithPrepayment xmlns="http://tempuri.org/CallCenterWs/CallCenterWs">
                      <sCorpCode>CCBZ</sCorpCode>
                      <sLocationCode>' . $location . '</sLocationCode>
                      <sCorpUserName>Ali:::MAMMOTHSW28BGD9OUBBX</sCorpUserName>
                      <sCorpPassword>Currie131!</sCorpPassword>
                      <iTenantID>' . $tenant_id . '</iTenantID>
                      <iNumberOfMonthsPrepay>' . $number_of_future_periods . '</iNumberOfMonthsPrepay>
                    </CustomerAccountsBalanceDetailsWithPrepayment>
                  </soap:Body>
                </soap:Envelope>',
            CURLOPT_HTTPHEADER => array(
                'SOAPAction: http://tempuri.org/CallCenterWs/CallCenterWs/CustomerAccountsBalanceDetailsWithPrepayment',
                'Content-Type: text/xml; charset=utf-8',
            ),
        ));

        $response = curl_exec($curl);
        curl_close($curl);
        return $response;
    }
}
