<?php
defined('BASEPATH') or exit('No direct script access allowed');

class ProfileController extends CI_Controller
{

    public function index()
    {
        // If logged in, redirect to profile page
        if ($this->session->userdata('user_data')) {

            $this->load->view('profile/profile_home_view');
        } else {
            // Show login view
            redirect('login');
        }
    }

    public function getTenantInfoById()
    {
        // Check if the user is logged in by checking session data
        $user_data = $this->session->userdata('user_data');

        if (!$user_data || !$user_data['logged_in']) {
            // If the user is not logged in, return a JSON error response
            echo json_encode(['success' => false, 'error' => 'User not logged in.']);
            return;
        }

        // Get the user_id (iTenantID) from session
        $tenant_id = $user_data['user_id'];
        $location = $user_data['location'];

        // Set up the SOAP request
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
                        <TenantInfoByTenantID xmlns="http://tempuri.org/CallCenterWs/CallCenterWs">
                            <sCorpCode>CCBZ</sCorpCode>
                            <sLocationCode>' . $location . '</sLocationCode>
                            <sCorpUserName>Ali:::MAMMOTHSW28BGD9OUBBX</sCorpUserName>
                            <sCorpPassword>Currie131!</sCorpPassword>
                            <iTenantID>' . $tenant_id . '</iTenantID>
                        </TenantInfoByTenantID>
                    </soap:Body>
                </soap:Envelope>',
            CURLOPT_HTTPHEADER => array(
                'SOAPAction: http://tempuri.org/CallCenterWs/CallCenterWs/TenantInfoByTenantID',
                'Content-Type: text/xml; charset=utf-8'
            ),
        ));

        // Execute the request and get the response
        $response = curl_exec($curl);

        // Handle errors during the CURL execution
        if ($response === false) {
            curl_close($curl);
            echo json_encode(['success' => false, 'error' => 'Failed to make the SOAP request.']);
            return;
        }

        curl_close($curl);

        // Parse the SOAP XML response
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($response);

        if ($xml === false) {
            echo json_encode(['success' => false, 'error' => 'Failed to parse SOAP response.']);
            return;
        }

        // Register namespaces and extract the needed data
        $xml->registerXPathNamespace('soap', 'http://schemas.xmlsoap.org/soap/envelope/');
        $xml->registerXPathNamespace('diffgr', 'urn:schemas-microsoft-com:xml-diffgram-v1');

        // Check for the failure response first (Ret_Code and Ret_Msg)
        $error_code = $xml->xpath('//diffgr:diffgram/NewDataSet/RT/Ret_Code');
        $error_msg = $xml->xpath('//diffgr:diffgram/NewDataSet/RT/Ret_Msg');

        if (!empty($error_code) && (int)$error_code[0] < 0) {
            // Handle error response (e.g., Invalid Tenant ID or any negative Ret_Code)
            echo json_encode(['success' => false, 'error' => (string)$error_msg[0]]);
            return;
        }

        // Extract tenant data from the response (for success case)
        $tenant_data = $xml->xpath('//diffgr:diffgram/NewDataSet/Table');

        if (!empty($tenant_data)) {
            // Convert the tenant data to an associative array for JSON response
            $tenant_info = [
                'TenantID' => (string) $tenant_data[0]->TenantID,
                'SiteID' => (string) $tenant_data[0]->SiteID,
                'EmployeeID' => (string) $tenant_data[0]->EmployeeID,
                'sAccessCode' => (string) $tenant_data[0]->sAccessCode,
                'sWebPassword' => (string) $tenant_data[0]->sWebPassword,
                'sFName' => (string) $tenant_data[0]->sFName,
                'sLName' => (string) $tenant_data[0]->sLName,
                'sCompany' => (string) $tenant_data[0]->sCompany,
                'sAddr1' => (string) $tenant_data[0]->sAddr1,
                'sCity' => (string) $tenant_data[0]->sCity,
                'sRegion' => (string) $tenant_data[0]->sRegion,
                'sPostalCode' => (string) $tenant_data[0]->sPostalCode,
                'sCountry' => (string) $tenant_data[0]->sCountry,
                'sPhone' => (string) $tenant_data[0]->sPhone,
                'sEmail' => (string) $tenant_data[0]->sEmail,
                'sMobile' => (string) $tenant_data[0]->sMobile,
                'bHasActiveLedger' => (string) $tenant_data[0]->bHasActiveLedger,
                'bAllowedFacilityAccess' => (string) $tenant_data[0]->bAllowedFacilityAccess,
                'dDOB' => (string) $tenant_data[0]->dDOB,
            ];

            // Return the tenant information as a JSON response
            echo json_encode(['success' => true, 'tenant_info' => $tenant_info]);
            return;
        } else {
            // No tenant data found
            echo json_encode(['success' => false, 'error' => 'No tenant information found.']);
            return;
        }
    }
}
