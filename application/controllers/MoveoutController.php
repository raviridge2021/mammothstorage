<?php
defined('BASEPATH') or exit('No direct script access allowed');

class MoveoutController extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
    }

    // Function to handle auto-payment update
    public function scheduleMoveout()
    {
        // Get user session data
        $user_data = $this->session->userdata('user_data');

        // If user is not logged in, return an error
        if (!$user_data || !$user_data['logged_in']) {
            echo json_encode(['success' => false, 'error' => 'User not logged in.']);
            return;
        }

        // Decode the incoming JSON request
        $requestData = json_decode($this->input->raw_input_stream, true);

        // Validate required fields
        if (
            !isset($requestData['ledgerID']) || !isset($requestData['dateScheduledOut'])
        ) {
            echo json_encode(['success' => false, 'error' => 'All required fields must be provided.']);
            return;
        }

        $ledgerID = $requestData['ledgerID'];
        $dateScheduledOut = $requestData['dateScheduledOut'];
        $dateScheduledOutWithCancel = $requestData['dateScheduledOutWithCancel'];
        $location = $user_data['location'];

        // Prepare the SOAP request
        $response = $this->updateScheduledOut($location, $ledgerID, $dateScheduledOut, $dateScheduledOutWithCancel);

        if (!$response) {
            echo json_encode(['success' => false, 'error' => 'Failed to set schedule moveout.']);
            return;
        }

        // Parse the XML response
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($response);

        if ($xml === false) {
            echo json_encode(['success' => false, 'error' => 'Failed to parse response from server.']);
            return;
        }

        $xml->registerXPathNamespace('soap', 'http://schemas.xmlsoap.org/soap/envelope/');
        $xml->registerXPathNamespace('diffgr', 'urn:schemas-microsoft-com:xml-diffgram-v1');

        // Handle errors in the SOAP response
        $error_code = $xml->xpath('//diffgr:diffgram/NewDataSet/RT/Ret_Code');
        $error_msg = $xml->xpath('//diffgr:diffgram/NewDataSet/RT/Ret_Msg');

        if (!empty($error_code) && (int)$error_code[0] < 0) {
            echo json_encode(['success' => false, 'error' => (string)$error_msg[0]]);
            return;
        }

        // Success response
        echo json_encode(['success' => true, 'message' => 'Your moveout has been successfully scheduled. You will receive a confirmation email shortly.']);
    }

    // Function to make the SOAP request for updating billing info
    private function updateScheduledOut($location, $ledgerID, $dateScheduledOut, $dateScheduledOutWithCancel = false)
    {


        $soapEnvelope = '
    <soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
      <soap:Body>
        <ScheduleMoveOut xmlns="http://tempuri.org/CallCenterWs/CallCenterWs">
          <sCorpCode>CCBZ</sCorpCode>
          <sLocationCode>' . $location . '</sLocationCode>
          <sCorpUserName>Ali:::MAMMOTHSW28BGD9OUBBX</sCorpUserName>
          <sCorpPassword>Currie131!</sCorpPassword>
          <iLedgerID>' . $ledgerID . '</iLedgerID>';

        if ($dateScheduledOutWithCancel == false) {
            $soapEnvelope .= '
          <dScheduledOut>' . $dateScheduledOut . '</dScheduledOut>';
        }

        $soapEnvelope .= '
        </ScheduleMoveOut>
      </soap:Body>
    </soap:Envelope>';


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
            CURLOPT_POSTFIELDS => $soapEnvelope,
            CURLOPT_HTTPHEADER => array(
                'SOAPAction: http://tempuri.org/CallCenterWs/CallCenterWs/ScheduleMoveOut',
                'Content-Type: text/xml; charset=utf-8',
            ),
        ));

        $response = curl_exec($curl);
        curl_close($curl);
        return $response;
    }
}
