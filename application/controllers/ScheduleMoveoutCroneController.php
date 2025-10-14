<?php
defined('BASEPATH') or exit('No direct script access allowed');

class ScheduleMoveoutCroneController extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->library('email');
        $this->load->database();
    }

    // Function to send move out reminders using SOAP API data
    public function scheduleMoveoutReminders()
    {
        // Get tomorrow's date
        $tomorrow = date('Y-m-d', strtotime('+1 day'));
        
        // Get all locations (you might need to define these or get from config)
        $locations = ['L001']; // Add your location codes here
        
        foreach ($locations as $location) {
            // Get scheduled moveouts from SOAP API
            $scheduledMoveouts = $this->getScheduledMoveoutsFromAPI($location, $tomorrow);
            
            if (!empty($scheduledMoveouts)) {
                foreach ($scheduledMoveouts as $moveout) {
                    $this->sendScheduleMoveoutReminderEmail($moveout);
                }
            }
        }
    }

    // Get scheduled moveouts from SOAP API
    private function getScheduledMoveoutsFromAPI($location, $targetDate)
    {
        // You'll need to implement this method based on your SOAP API
        // This is a placeholder - you'll need to call the appropriate SOAP method
        
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
                <GetScheduledMoveouts xmlns="http://tempuri.org/CallCenterWs/CallCenterWs">
                  <sCorpCode>CCBZ</sCorpCode>
                  <sLocationCode>' . $location . '</sLocationCode>
                  <sCorpUserName>Ali:::MAMMOTHSW28BGD9OUBBX</sCorpUserName>
                  <sCorpPassword>Currie131!</sCorpPassword>
                  <sTargetDate>' . $targetDate . '</sTargetDate>
                </GetScheduledMoveouts>
              </soap:Body>
            </soap:Envelope>',
            CURLOPT_HTTPHEADER => [
                'SOAPAction: http://tempuri.org/CallCenterWs/CallCenterWs/GetScheduledMoveouts',
                'Content-Type: text/xml; charset=utf-8',
            ],
        ]);

        $response = curl_exec($curl);
        curl_close($curl);
        
        if (!$response) {
            return [];
        }

        // Parse the XML response
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($response);

        if ($xml === false) {
            return [];
        }

        $xml->registerXPathNamespace('soap', 'http://schemas.xmlsoap.org/soap/envelope/');
        $xml->registerXPathNamespace('diffgr', 'urn:schemas-microsoft-com:xml-diffgram-v1');

        // Handle errors
        $error_code = $xml->xpath('//diffgr:diffgram/NewDataSet/RT/Ret_Code');
        if (!empty($error_code) && (int)$error_code[0] < 0) {
            return [];
        }

        // Extract scheduled moveouts
        $moveouts = $xml->xpath('//diffgr:diffgram/NewDataSet/Table');
        $scheduledMoveouts = [];

        foreach ($moveouts as $moveout) {
            $scheduledMoveouts[] = [
                'email' => (string)$moveout->Email,
                'name' => (string)$moveout->CustomerName,
                'moveout_date' => (string)$moveout->MoveoutDate,
                'unit_name' => (string)$moveout->UnitName,
                'tenant_id' => (int)$moveout->TenantID
            ];
        }

        return $scheduledMoveouts;
    }

    private function sendScheduleMoveoutReminderEmail($moveoutData)
    {
        $this->email->from('noreply@mammothstorage.com.au', 'Mammoth Storage');
        $this->email->to($moveoutData['email']);
        
        $this->email->subject('Schedule Move Out Reminder - Tomorrow');
        
        $message = "
        <html>
        <body>
        <h2>Schedule Move Out Reminder</h2>
        <p>Dear " . $moveoutData['name'] . ",</p>
        <p>This is a friendly reminder that your scheduled move out is tomorrow.</p>
        <p><strong>Unit:</strong> " . $moveoutData['unit_name'] . "</p>
        <p><strong>Move Out Date:</strong> " . date('d/m/Y', strtotime($moveoutData['moveout_date'])) . "</p>
        <p>Please ensure all items are removed by the end of tomorrow.</p>
        <p>Thank you for choosing Mammoth Storage.</p>
        <br>
        <p>Best regards,<br>Mammoth Storage Team</p>
        </body>
        </html>
        ";
        
        $this->email->message($message);
        $this->email->send();
    }
}
