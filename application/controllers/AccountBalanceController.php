<?php
defined('BASEPATH') or exit('No direct script access allowed');

class AccountBalanceController extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
    }

    public function getAccountBalance()
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
        $response = $this->fetchAccountBalance($location, $tenant_id);

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
        $balances = $xml->xpath('//diffgr:diffgram/NewDataSet/Table1');
        $balance_data = [];

        foreach ($balances as $balance) {
            $unit_id = (int)$balance->UnitID;
            $unit_name = (string)$balance->sUnitName; // Extract unit name
            $balance_amount = (float)$balance->dcBalance;

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
            'payment' => $payments
        ]));
    }

    // Function to fetch account balance
    private function fetchAccountBalance($location, $tenant_id)
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
}
