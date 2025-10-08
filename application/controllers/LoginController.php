<?php
defined('BASEPATH') or exit('No direct script access allowed');

class LoginController extends CI_Controller
{

    public function __construct()
    {
        parent::__construct();
    }

    // Default index method that displays the login form or redirects to the profile page if already logged in
    public function index()
    {
        // If logged in, redirect to profile page
        if ($this->session->userdata('user_data')) {
            redirect('profile');
        } else {
            // Show login view
            $this->load->view('auth/profile_login_view');
        }
    }


    public function viewResetPasswordView()
    {
        // Check if the user is logged in
        if ($this->session->userdata('user_data')) {
            redirect('profile');
        } else {
            // Get the token from the query string
            $token = $this->input->get('token');

            // Define your encryption key (must be the same as used during encryption)
            $encryptionKey = 'mammoth_account_reset_password_key'; // Your encryption key

            if ($token) {
                // Decode the token and separate the IV and encrypted data
                list($iv, $encryptedData) = explode('::', base64_decode($token), 2); // Get the IV and encrypted data

                // Decrypt the token to get the combined data
                $decryptedData = openssl_decrypt($encryptedData, 'aes-256-cbc', $encryptionKey, 0, $iv); // Decrypt the combined data

                if ($decryptedData) {
                    // Parse the decrypted JSON string
                    $data2 = json_decode($decryptedData, true);
                    $data['email'] = $data2['email']; // Set the email for the view
                    $data['location'] = $data2['location']; // Retrieve the location
                    $data['location_name'] = $data2['location_name']; // Retrieve the location

                    // Now you can use $email and $location as needed
                } else {
                    // Handle invalid token case
                    $data['error'] = 'Invalid or expired token.';
                }
            } else {
                // Handle missing token case
                $data['error'] = 'Token not provided.';
            }

            // Load the view with the email (if valid)
            $this->load->view('auth/profile_forgot_password_view', isset($data) ? $data : []);
        }
    }

    public function updateAccountPassword()
    {
        // Only allow AJAX requests
        if (!$this->input->is_ajax_request()) {
            show_error('No direct access allowed', 403);
        }

        // Get the input JSON data (email and password)
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);


        // Check if email and password are provided
        if (!isset($data['location'])) {
            echo json_encode(['success' => false, 'error' => 'Location is required.']);
            return;
        }

        // Check if email and password are provided
        if (!isset($data['email']) || !isset($data['new_password'])) {
            echo json_encode(['success' => false, 'error' => 'Email and password are required.']);
            return;
        }

        $location = $data['location'];
        $email = $data['email'];
        $new_password = $data['new_password'];


        // Make the SOAP API request
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
            CURLOPT_POSTFIELDS => '<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
              <soap:Body>
                <TenantSearchDetailed xmlns="http://tempuri.org/CallCenterWs/CallCenterWs">
                  <sCorpCode>CCBZ</sCorpCode>
                  <sLocationCode>' . $location . '</sLocationCode>
                  <sCorpUserName>Ali:::MAMMOTHSW28BGD9OUBBX</sCorpUserName>
                  <sCorpPassword>Currie131!</sCorpPassword>
                  <sEmailAddress>' . $email . '</sEmailAddress>
                </TenantSearchDetailed>
              </soap:Body>
            </soap:Envelope>',
            CURLOPT_HTTPHEADER => array(
                'SOAPAction: http://tempuri.org/CallCenterWs/CallCenterWs/TenantSearchDetailed',
                'Content-Type: text/xml; charset=utf-8'
            ),
        ));

        // Execute the SOAP request
        $response = curl_exec($curl);
        curl_close($curl);

        // Handle SOAP response parsing and error handling
        if (!$response) {
            echo json_encode(["success" => false, "error" => "Failed to connect to the API."]);
            return;
        }

        // Parse the XML response
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($response);

        if ($xml === false) {
            echo json_encode(["success" => false, "error" => "Failed to parse SOAP response."]);
            return;
        }

        // Register namespaces for parsing
        $xml->registerXPathNamespace('soap', 'http://schemas.xmlsoap.org/soap/envelope/');
        $xml->registerXPathNamespace('diffgr', 'urn:schemas-microsoft-com:xml-diffgram-v1');

        // Extract the first Table element
        $firstTable = $xml->xpath('//diffgr:diffgram/NewDataSet/Table[1]');

        // Check if first RT element exists
        if (!empty($firstTable)) {
            // Extract relevant data from the first Table element
            $tenantData = $firstTable[0];
            $tenant_id = (string)$tenantData->TenantID;


            // Make the SOAP API request
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
                CURLOPT_POSTFIELDS => '<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
              <soap:Body>
                    <TenantLoginAndSecurityUpdate xmlns="http://tempuri.org/CallCenterWs/CallCenterWs">
                    <sCorpCode>CCBZ</sCorpCode>
                    <sLocationCode>' . $location . '</sLocationCode>
                    <sCorpUserName>Ali:::MAMMOTHSW28BGD9OUBBX</sCorpUserName>
                    <sCorpPassword>Currie131!</sCorpPassword>
                    <TenantID>' . $tenant_id . '</TenantID>
                    <sEmail>' . $email . '</sEmail>
                    <sWebPassword>' . $new_password . '</sWebPassword>
                    </TenantLoginAndSecurityUpdate>
                </soap:Body>
            </soap:Envelope>',
                CURLOPT_HTTPHEADER => array(
                    'SOAPAction: http://tempuri.org/CallCenterWs/CallCenterWs/TenantLoginAndSecurityUpdate',
                    'Content-Type: text/xml; charset=utf-8'
                ),
            ));

            // Execute the SOAP request
            $response = curl_exec($curl);
            curl_close($curl);

            // Handle SOAP response parsing and error handling
            if (!$response) {
                echo json_encode(["success" => false, "error" => "Failed to connect to the API."]);
                return;
            }

            // Parse the XML response
            libxml_use_internal_errors(true);
            $xml = simplexml_load_string($response);

            if ($xml === false) {
                echo json_encode(["success" => false, "error" => "Failed to parse SOAP response."]);
                return;
            }

            // Register namespaces for parsing
            $xml->registerXPathNamespace('soap', 'http://schemas.xmlsoap.org/soap/envelope/');
            $xml->registerXPathNamespace('diffgr', 'urn:schemas-microsoft-com:xml-diffgram-v1');


            // Handle errors
            $error_code = $xml->xpath('//diffgr:diffgram/NewDataSet/RT/Ret_Code');
            $error_msg = $xml->xpath('//diffgr:diffgram/NewDataSet/RT/Ret_Msg');

            if (!empty($error_code) && (int)$error_code[0] < 0) {
                echo json_encode(['success' => false, 'error' => (string)$error_msg[0]]);
                return;
            }

            $tenantInfo = [
                'SiteID' => (string)$tenantData->SiteID,
                'TenantID' => (string)$tenantData->TenantID,
                'sLocationCode' => (string)$tenantData->sLocationCode,
                'sFName' => (string)$tenantData->sFName,
                'sLName' => (string)$tenantData->sLName,
                'sCompany' => (string)$tenantData->sCompany,
                'sAddr1' => (string)$tenantData->sAddr1,
                'sCity' => (string)$tenantData->sCity,
                'sPostalCode' => (string)$tenantData->sPostalCode,
                'sEmail' => (string)$tenantData->sEmail,
                'bHasActiveLedger' => (string)$tenantData->bHasActiveLedger,
            ];

            // Successful response with tenant info
            echo json_encode([
                'success' => true,
                'tenant' => $tenantInfo,
            ]);
        } else {
            // If no Table element is found
            echo json_encode(['success' => false, 'error' => 'The system is unable to find this account.']);
        }
    }

    // Method to authenticate the user using SOAP API
    public function authenticate()
    {
        // Only allow AJAX requests
        if (!$this->input->is_ajax_request()) {
            show_error('No direct access allowed', 403);
        }

        // Get the input JSON data (email and password)
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);

        // Check if email and password are provided
        if (!isset($data['email']) || !isset($data['password'])) {
            echo json_encode(['success' => false, 'error' => 'Email and password are required.']);
            return;
        }

        $email = $data['email'];
        $password = $data['password'];
        $location = $data['location'];

        // Make the SOAP API request
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
            CURLOPT_POSTFIELDS => '<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
              <soap:Body>
                <TenantLogin xmlns="http://tempuri.org/CallCenterWs/CallCenterWs">
                  <sCorpCode>CCBZ</sCorpCode>
                  <sLocationCode>' . $location . '</sLocationCode>
                  <sCorpUserName>Ali:::MAMMOTHSW28BGD9OUBBX</sCorpUserName>
                  <sCorpPassword>Currie131!</sCorpPassword>
                  <sTenantLogin>' . $email . '</sTenantLogin>
                  <sTenantPassword>' . $password . '</sTenantPassword>
                </TenantLogin>
              </soap:Body>
            </soap:Envelope>',
            CURLOPT_HTTPHEADER => array(
                'SOAPAction: http://tempuri.org/CallCenterWs/CallCenterWs/TenantLogin',
                'Content-Type: text/xml; charset=utf-8'
            ),
        ));

        // Execute the SOAP request
        $response = curl_exec($curl);
        curl_close($curl);

        // Handle SOAP response parsing and error handling
        if (!$response) {
            echo json_encode(["success" => false, "error" => "Failed to connect to the API."]);
            return;
        }

        // Parse the XML response
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($response);

        if ($xml === false) {
            echo json_encode(["success" => false, "error" => "Failed to parse SOAP response."]);
            return;
        }

        // Register namespaces for parsing
        $xml->registerXPathNamespace('soap', 'http://schemas.xmlsoap.org/soap/envelope/');
        $xml->registerXPathNamespace('diffgr', 'urn:schemas-microsoft-com:xml-diffgram-v1');

        // Extract the first RT element where bHasActiveLedger is true, regardless of its position
        $activeLedgerRT = $xml->xpath('//diffgr:diffgram/NewDataSet/RT[bHasActiveLedger="true"]');

        // Check if an RT element with bHasActiveLedger = true exists
        if (!empty($activeLedgerRT)) {
            // Use the first RT element with bHasActiveLedger = true
            $firstActiveLedgerRT = $activeLedgerRT[0];
            $retCode = (string)$firstActiveLedgerRT->Ret_Code;

            if ((int)$retCode == -1) {
                // Handle failed login (Ret_Code = -1)
                $retMsg = (string)$firstActiveLedgerRT->Ret_Msg;
                echo json_encode(['success' => false, 'error' => $retMsg]);
            } else {
                // Successful login
                $sWebPassword = (string)$firstActiveLedgerRT->sWebPassword;
                $hasActiveLedger = (string)$firstActiveLedgerRT->bHasActiveLedger === 'true' ? true : false;

                // Prepare session data with the RT element having bHasActiveLedger = true
                $session_data = array(
                    'email' => $email,
                    'location' => $location,
                    'user_id' => $retCode,  // Storing Ret_Code as user_id
                    'logged_in' => true
                );

                // Store in session
                $this->session->set_userdata('user_data', $session_data);

                // Return success response with extracted data
                echo json_encode([
                    'success' => true
                ]);
            }
        } else {
            // If no RT element with bHasActiveLedger = true is found
            echo json_encode(['success' => false, 'error' => 'Invalid logon credentials.']);
        }
    }

    // Method to handle user logout
    public function logout()
    {
        // Unset the user_data session key
        $this->session->unset_userdata('user_data');

        // Optionally, destroy the entire session
        $this->session->sess_destroy();

        // Redirect to the login page
        redirect('login');
    }
}
