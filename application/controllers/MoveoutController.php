<?php
defined('BASEPATH') or exit('No direct script access allowed');

class MoveoutController extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        // Remove this line: $this->load->database(); // Load database for direct queries
    }

    // Function to handle moveout scheduling with rent calculation
    public function scheduleMoveout()
    {
        
        // Get user session data
        $user_data = $this->session->userdata('user_data');

        // print_r($user_data['location']);die();

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
        $dateScheduledOutWithCancel = $requestData['dateScheduledOutWithCancel'] ?? false;
        $location = $user_data['location'];

        // NEW: Calculate rent due if not cancelling
        if (!$dateScheduledOutWithCancel) {
            $rentCalculation = $this->calculateRentDue($ledgerID, $dateScheduledOut, $location);
            
            if (!$rentCalculation['success']) {
                echo json_encode(['success' => false, 'error' => $rentCalculation['error']]);
                return;
            }

            // If there's rent due, return the calculation for payment prompt
            if ($rentCalculation['rent_due'] > 0) {
                echo json_encode([
                    'success' => false, 
                    'requires_payment' => true,
                    'rent_calculation' => $rentCalculation
                ]);
                return;
            }
        }

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

        // Send confirmation email with unit/location details
        $unitInfo = $this->getUnitInfoByLedgerID($ledgerID, $location);
        $unitName = $unitInfo['unit_name'] ?? '';
        $locationName = $unitInfo['location_name'] ?? '';
        $this->sendMoveoutConfirmationEmail($user_data, $dateScheduledOut, $unitName, $locationName);

        // Success response
        echo json_encode(['success' => true, 'message' => 'Your moveout has been successfully scheduled. You will receive a confirmation email shortly.']);
    }

    // NEW: Function to process payment
    public function processPayment()
    {
        // Get user session data
        $user_data = $this->session->userdata('user_data');

        if (!$user_data || !$user_data['logged_in']) {
            echo json_encode(['success' => false, 'error' => 'User not logged in.']);
            return;
        }

        // Decode the incoming JSON request
        $requestData = json_decode($this->input->raw_input_stream, true);

        // Validate required fields
        if (!isset($requestData['ledgerID']) || !isset($requestData['amount']) || !isset($requestData['dateScheduledOut'])) {
            echo json_encode(['success' => false, 'error' => 'All required fields must be provided.']);
            return;
        }

        $ledgerID = $requestData['ledgerID'];
        $amount = $requestData['amount'];
        $dateScheduledOut = $requestData['dateScheduledOut'];
        $location = $user_data['location'];

        // Skip payment processing and proceed directly with moveout scheduling
        $response = $this->updateScheduledOut($location, $ledgerID, $dateScheduledOut, false);

        if (!$response) {
            echo json_encode(['success' => false, 'error' => 'Failed to schedule moveout.']);
            return;
        }

        echo json_encode([
            'success' => true, 
            'message' => 'Moveout scheduled successfully.',
            'rent_due' => $amount // Include the calculated rent amount for reference
        ]);
    }

    // NEW: Function to process payment with payment gateway
    private function processPaymentGateway($amount, $ledgerID, $user_data)
    {
        try {
            // For moveout payments, we don't process real payments
            // Just generate a reference for tracking
            $paymentReference = 'MOVE_OUT_' . time() . '_' . $ledgerID;
            
            // Log the payment calculation (optional)
            error_log("Moveout payment calculated: LedgerID: $ledgerID, Amount: $amount, Reference: $paymentReference");
            
            return [
                'success' => true,
                'reference' => $paymentReference,
                'message' => 'Moveout payment calculated successfully'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Payment calculation failed: ' . $e->getMessage()
            ];
        }
    }

    // NEW: Function to log payment in database
    private function logPayment($ledgerID, $amount, $reference, $user_data)
    {
        // Instead of database insert, make SOAP API call to process payment
        $location = $user_data['location'];
        $tenant_id = $user_data['user_id'];
        
        // Use the existing SOAP API to process payment
        $response = $this->processPaymentViaSOAP($location, $tenant_id, $ledgerID, $amount, $reference);
        
        if (!$response) {
            throw new Exception('Failed to process payment via SOAP API');
        }
        
        // Parse the SOAP response to check for errors
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($response);
        
        if ($xml === false) {
            throw new Exception('Failed to parse SOAP response');
        }
        
        $xml->registerXPathNamespace('soap', 'http://schemas.xmlsoap.org/soap/envelope/');
        $xml->registerXPathNamespace('diffgr', 'urn:schemas-microsoft-com:xml-diffgram-v1');
        
        // Check for errors in the SOAP response
        $error_code = $xml->xpath('//diffgr:diffgram/NewDataSet/RT/Ret_Code');
        $error_msg = $xml->xpath('//diffgr:diffgram/NewDataSet/RT/Ret_Msg');
        
        if (!empty($error_code) && (int)$error_code[0] < 0) {
            throw new Exception('SOAP API Error: ' . (string)$error_msg[0]);
        }
        
        return true; // Payment processed successfully
    }

    // Add this new method to handle SOAP payment processing
    private function processPaymentViaSOAP($location, $tenant_id, $ledgerID, $amount, $reference)
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
                <PaymentMultipleWithSource xmlns="http://tempuri.org/CallCenterWs/CallCenterWs">
                    <sCorpCode>CCBZ</sCorpCode>
                    <sLocationCode>' . $location . '</sLocationCode>
                    <sCorpUserName>Ali:::MAMMOTHSW28BGD9OUBBX</sCorpUserName>
                    <sCorpPassword>Currie131!</sCorpPassword>
                    <iTenantID>' . $tenant_id . '</iTenantID>
                    <sUnitIDs>' . $ledgerID . '</sUnitIDs>
                    <sPaymentAmounts>' . $amount . '</sPaymentAmounts>
                    <iCreditCardType>1</iCreditCardType>
                    <sCreditCardNumber>0000000000000000</sCreditCardNumber>
                    <sCreditCardCVV>000</sCreditCardCVV>
                    <dExpirationDate>12/25</dExpirationDate>
                    <sBillingName>Moveout Payment</sBillingName>
                    <bTestMode>true</bTestMode>
                    <iSource>10</iSource>
                </PaymentMultipleWithSource>
              </soap:Body>
            </soap:Envelope>',
            CURLOPT_HTTPHEADER => [
                'SOAPAction: http://tempuri.org/CallCenterWs/CallCenterWs/PaymentMultipleWithSource',
                'Content-Type: text/xml; charset=utf-8',
            ],
        ]);
        
        $response = curl_exec($curl);
        curl_close($curl);
        return $response;
    }

    // NEW: Function to calculate rent due using your exact formula
    private function calculateRentDue($ledgerID, $scheduledOutDate, $location)
    {
        try {
            // Get paid through date
            $paidThroughDate = $this->getPaidThroughDateByLedgerID($location, $ledgerID);
            
            if (!$paidThroughDate) {
                return ['success' => false, 'error' => 'Could not retrieve paid through date.'];
            }

            // Get unit rent rate from database using UnitID
            $unitRentRate = $this->getUnitRentRateByLedgerID($ledgerID, $location);
            
            if (!$unitRentRate) {
                return ['success' => false, 'error' => 'Could not retrieve unit rent rate.'];
            }

            // Convert dates for calculation
            $paidThrough = DateTime::createFromFormat('d/m/Y', $paidThroughDate);
            $scheduledOut = DateTime::createFromFormat('Y-m-d', $scheduledOutDate);
            
            if (!$paidThrough || !$scheduledOut) {
                return ['success' => false, 'error' => 'Invalid date format.'];
            }

            // Calculate days between paid through and scheduled out
            $interval = $paidThrough->diff($scheduledOut);
            $daysBetween = $interval->days;

            if ($daysBetween <= 0) {
                return [
                    'success' => true,
                    'rent_due' => 0,
                    'paid_through_date' => $paidThroughDate,
                    'scheduled_out_date' => $scheduledOutDate,
                    'days_between' => 0,
                    'daily_rate' => 0,
                    'message' => 'No rent due - move out date is on or before paid through date.'
                ];
            }

                        // Calculate days between paid through and scheduled out (SIGNED)
            // If scheduledOut is on or before paidThrough, no rent is due.
            $daysBetween = (int)$paidThrough->diff($scheduledOut)->format('%r%a');

            if ($daysBetween <= 0) {
                return [
                    'success' => true,
                    'rent_due' => 0,
                    'paid_through_date' => $paidThroughDate,
                    'scheduled_out_date' => $scheduledOutDate,
                    'days_between' => 0,
                    'daily_rate' => 0,
                    'message' => 'No rent due - move out date is on or before paid through date.'
                ];
            }

            // Calculate using your exact formula with proper rounding:
            $annualRate = $unitRentRate * 12; // Monthly rate * 12 months
            $dailyRate = $annualRate / 365;   // Annual rate / 365 days
            $rentDue = $daysBetween * $dailyRate; // Days * daily rate

            return [
                'success' => true,
                'rent_due' => round($rentDue, 3), // Round to 3 decimal places
                'paid_through_date' => $paidThroughDate,
                'scheduled_out_date' => $scheduledOutDate,
                'days_between' => $daysBetween,
                'monthly_rate' => round($unitRentRate, 3),
                'annual_rate' => round($annualRate, 3),
                'daily_rate' => round($dailyRate, 3),
                'message' => "Rent due: $" . round($rentDue, 3) . " for " . $daysBetween . " days"
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => 'Error calculating rent: ' . $e->getMessage()];
        }
    }

    // NEW: Function to get unit rent rate from SOAP API using LedgerID
    private function getUnitRentRateByLedgerID($ledgerID, $location)
    {
        // Get user session data to get tenant_id
        $user_data = $this->session->userdata('user_data');
        $tenant_id = $user_data['user_id'];

        // Use the UnitsInformation SOAP API to get unit information with rent rates
        $response = $this->fetchUnitsInformation($location);

        if (!$response) {
            return null;
        }

        // Parse the XML response
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($response);

        if ($xml === false) {
            return null;
        }

        $xml->registerXPathNamespace('soap', 'http://schemas.xmlsoap.org/soap/envelope/');
        $xml->registerXPathNamespace('diffgr', 'urn:schemas-microsoft-com:xml-diffgram-v1');

        // Handle errors
        $error_code = $xml->xpath('//diffgr:diffgram/NewDataSet/RT/Ret_Code');
        $error_msg = $xml->xpath('//diffgr:diffgram/NewDataSet/RT/Ret_Msg');

        if (!empty($error_code) && (int)$error_code[0] < 0) {
            return null;
        }

        // Extract unit information and find the matching unit
        $units = $xml->xpath('//diffgr:diffgram/NewDataSet/Table');

        // First, get the UnitID for this ledger from account balance API
        $unitID = $this->getUnitIDByLedgerID($ledgerID, $location);
        
        if (!$unitID) {
            return null;
        }

        // Now find the rent rate for this UnitID
        foreach ($units as $unit) {
            $unit_id = (int)$unit->UnitID;
            
            if ($unit_id == $unitID) {
                $standard_rate = (float)$unit->dcStdRate;
                $tax_rate = (float)$unit->dcTax1Rate;
                $tax_amount = $standard_rate * ($tax_rate / 100);
                $total_rate_with_tax = $standard_rate + $tax_amount;
                
                // Debug output
                // echo "<h4>Rate Calculation:</h4>";
                // echo "Standard Rate: $" . $standard_rate . "<br>";
                // echo "Tax Rate: " . $tax_rate . "%<br>";
                // echo "Tax Amount: $" . round($tax_amount, 2) . "<br>";
                // echo "Total Rate (with tax): $" . round($total_rate_with_tax, 2) . "<br>";
                
                return $total_rate_with_tax;
            }
        }

        return null;
    }

    // NEW: Function to fetch account units (reuse existing logic)
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

    // NEW: Function to get paid through date
    private function getPaidThroughDateByLedgerID($location, $ledgerID)
    {
        // echo "<pre>"; print_r($ledgerID); die;
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
        // echo "<pre>"; print_r($response); die;
        return $this->parsePaidThroughDate($response);
    }

    // NEW: Function to parse paid through date
    private function parsePaidThroughDate($response)
    {
        if (!$response) {
            return null;
        }

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($response);

        if ($xml === false) {
            return null;
        }

        $xml->registerXPathNamespace('soap', 'http://schemas.xmlsoap.org/soap/envelope/');
        $xml->registerXPathNamespace('diffgr', 'urn:schemas-microsoft-com:xml-diffgram-v1');

        $error_code = $xml->xpath('//diffgr:diffgram/NewDataSet/RT/Ret_Code');
        $error_msg = $xml->xpath('//diffgr:diffgram/NewDataSet/RT/Ret_Msg');

        if (!empty($error_code) && (int)$error_code[0] < 0) {
            return null;
        }

        $ledgers = $xml->xpath('//diffgr:diffgram/NewDataSet/Table');
        $paidThru = "";

        foreach ($ledgers as $ledger) {
            $timestamp = (string)$ledger->dPaidThru;
            $date = (new DateTime($timestamp))->format('d/m/Y');
            $paidThru = $date;
        }

        return $paidThru;
    }

    // NEW: Function to send moveout confirmation email
    private function sendMoveoutConfirmationEmail($user_data, $moveoutDate, $unitName = '', $locationName = '')
    {
       
        $this->load->library('phpmailer_lib');
        $mail = $this->phpmailer_lib->load();

        $smtpData = [
            'smtpHost' => 'smtp.gmail.com',
            'smtpPort' => 587,
            'smtpUsername' => 'hello@mammothstorage.com.au',
            'smtpPassword' => 'ycpl dsjd xtkr hpss',
            'smtpFromEmail' => 'hello@mammothstorage.com.au',
            'smtpFromName' => 'Mammoth Storage',
        ];

        $mail->isSMTP();
        $mail->Host       = $smtpData['smtpHost'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $smtpData['smtpUsername'];
        $mail->Password   = $smtpData['smtpPassword'];
        $mail->SMTPSecure = 'tls';
        $mail->Port       = $smtpData['smtpPort'];

        $mail->setFrom($smtpData['smtpFromEmail'], $smtpData['smtpFromName']);
        $mail->addAddress($user_data['email']);
        $mail->addAddress('ronakp.dev@gmail.com');

        $mail->Subject = 'Move Out Scheduled - Confirmation';
        $mail->isHTML(true);

        // Embed the logo for use via CID in HTML
        $mail->addEmbeddedImage(FCPATH . 'assets/images/logo.jpg', 'mammoth_logo', 'logo.jpg');

        $userName = $this->getTenantName($user_data) ?? $user_data['email'];
        $prettyDate = date('d/m/Y', strtotime($moveoutDate));
        $unitDisplay = $unitName !== '' ? $unitName : 'your space';
        $locationDisplay = $locationName !== '' ? $locationName : 'Mammoth Storage';

        // Map review URLs by location CODE (not name)
        $reviewMap = [
            'L001' => 'https://g.page/r/CZLJrF9OKrcmEBM/review',  // Forest Glen
            'L002' => 'https://g.page/r/CYTDGLMO-ekpEAI/review',  // Maroochydore
            'L003' => 'https://g.page/r/CTyeCOCwc-3wEAI/review', // Nambour
            'L004' => 'https://g.page/r/CewXQ35F7WvBEAI/review', // Caloundra
            'L005' => 'https://g.page/r/CX-D-HGT9u5uEAI/review', // Hervey Bay
            'L006' => 'https://g.page/r/CRi80PpOTasXEBM/review', // Bundaberg
            'L007' => 'https://g.page/r/CSSfMhCc2FuYEBM/review', // Kunda Park
            'L008' => 'https://g.page/r/Cb5ZxGjx41GjEBM/review', // Gympie
        ];

        // Get the user's location code from session
        $userLocationCode = $user_data['location'] ?? '';
        
        // Find the review URL based on location code
        $reviewUrl = $reviewMap[$userLocationCode] ?? '';
        
        // Fallback to Nambour if no match found
        if ($reviewUrl === '') {
            $reviewUrl = 'https://g.page/r/CTyeCOCwc-3wEAI/review';
        }

        // Add debug logging to help you test locally
        log_message('info', 'Moveout email debug - Location Code: ' . $userLocationCode . ', Review URL: ' . $reviewUrl);

        $mail->Body = '<html><body>'
            . '<p>Dear ' . htmlspecialchars($userName) . ',</p>'
            . '<p>Thank you for notifying us of your scheduled move out date for space <strong>' . htmlspecialchars($unitDisplay) . '</strong> at <strong>' . htmlspecialchars($locationDisplay) . '</strong>. We have scheduled your move out date to be <strong>' . htmlspecialchars($prettyDate) . '</strong>.</p>'
            . '<p>If this date is likely to be postponed, please let us know as soon as possible in case we have found a new customer to take the space.</p>'
            . '<p>We really hope you enjoyed our service. Mammoth Storage is family owned and operated and your positive review can really help us. We would really appreciate it if you could take 30 seconds to leave us a Google Review.</p>'
            . '<p><a href="' . htmlspecialchars($reviewUrl) . '" target="_blank" rel="noopener noreferrer">Click here to leave a review.</a></p>'
            . '<br><p>Thank you for choosing to move freely with Mammoth Storage.</p>'
            . '<br><p>Best regards,<br>Mammoth Storage Team</p>'
            . '<p><img src="cid:mammoth_logo" alt="Mammoth Storage" style="margin-top:8px;width:160px;height:auto;"></p>'
            . '</body></html>';

        if (!$mail->send()) {
            log_message('error', 'Moveout email failed: ' . $mail->ErrorInfo);
            return false;
        }
        return true;
        
    
    }

    // NEW: Get unit and location display names for email by LedgerID
    private function getUnitInfoByLedgerID($ledgerID, $location)
    {
        $user_data = $this->session->userdata('user_data');
        $tenant_id = $user_data['user_id'];

        $response = $this->fetchAllAccountUnits($location, $tenant_id);
        if (!$response) {
            return ['unit_name' => '', 'location_name' => ''];
        }

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($response);
        if ($xml === false) {
            return ['unit_name' => '', 'location_name' => ''];
        }

        $xml->registerXPathNamespace('soap', 'http://schemas.xmlsoap.org/soap/envelope/');
        $xml->registerXPathNamespace('diffgr', 'urn:schemas-microsoft-com:xml-diffgram-v1');

        // Iterate balances dataset and match by LedgerID
        $rows = $xml->xpath('//diffgr:diffgram/NewDataSet/Table1');
        foreach ($rows as $row) {
            $rowLedgerId = (string)$row->LedgerID;
            if ($rowLedgerId == (string)$ledgerID) {
                $unitName = isset($row->sUnitName) ? (string)$row->sUnitName : '';
                // Try multiple likely fields for location name
                $locationName = isset($row->sLocationName) ? (string)$row->sLocationName : '';
                if ($locationName === '' && isset($row->LocationName)) {
                    $locationName = (string)$row->LocationName;
                }
                return [
                    'unit_name' => $unitName,
                    'location_name' => $locationName
                ];
            }
        }

        return ['unit_name' => '', 'location_name' => ''];
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

    // Add this new method to fetch units information with rent rates
    private function fetchUnitsInformation($location)
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
                <UnitsInformation xmlns="http://tempuri.org/CallCenterWs/CallCenterWs">
                  <sCorpCode>CCBZ</sCorpCode>
                  <sLocationCode>' . $location . '</sLocationCode>
                  <sCorpUserName>Ali:::MAMMOTHSW28BGD9OUBBX</sCorpUserName>
                  <sCorpPassword>Currie131!</sCorpPassword>
                </UnitsInformation>
              </soap:Body>
            </soap:Envelope>',
            CURLOPT_HTTPHEADER => [
                'SOAPAction: http://tempuri.org/CallCenterWs/CallCenterWs/UnitsInformation',
                'Content-Type: text/xml; charset=utf-8',
            ],
        ]);

        $response = curl_exec($curl);
        curl_close($curl);
        return $response;
    }

    // Keep the getUnitIDByLedgerID method as it's still needed
    private function getUnitIDByLedgerID($ledgerID, $location)
    {
        // Get user session data to get tenant_id
        $user_data = $this->session->userdata('user_data');
        $tenant_id = $user_data['user_id'];

        // Use the existing SOAP API to get unit information
        $response = $this->fetchAllAccountUnits($location, $tenant_id);

        if (!$response) {
            return null;
        }

        // Parse the XML response
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($response);

        if ($xml === false) {
            return null;
        }

        $xml->registerXPathNamespace('soap', 'http://schemas.xmlsoap.org/soap/envelope/');
        $xml->registerXPathNamespace('diffgr', 'urn:schemas-microsoft-com:xml-diffgram-v1');

        // Handle errors
        $error_code = $xml->xpath('//diffgr:diffgram/NewDataSet/RT/Ret_Code');
        $error_msg = $xml->xpath('//diffgr:diffgram/NewDataSet/RT/Ret_Msg');

        if (!empty($error_code) && (int)$error_code[0] < 0) {
            return null;
        }

        // Extract unit information
        $units = $xml->xpath('//diffgr:diffgram/NewDataSet/Table1');

        foreach ($units as $balance) {
            $unit_ledger_id = (int)$balance->LedgerID;
            $unit_id = (int)$balance->UnitID;

            if ($unit_ledger_id == $ledgerID) {
                return $unit_id;
            }
        }

        return null;
    }

    private function getTenantName($user_data)
    {
        $tenant_id = $user_data['user_id'];
        $location  = $user_data['location'];

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

        $response = curl_exec($curl);
        curl_close($curl);
        if (!$response) return null;

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($response);
        if ($xml === false) return null;

        $xml->registerXPathNamespace('soap', 'http://schemas.xmlsoap.org/soap/envelope/');
        $xml->registerXPathNamespace('diffgr', 'urn:schemas-microsoft-com:xml-diffgram-v1');

        $error_code = $xml->xpath('//diffgr:diffgram/NewDataSet/RT/Ret_Code');
        if (!empty($error_code) && (int)$error_code[0] < 0) return null;

        $rows = $xml->xpath('//diffgr:diffgram/NewDataSet/Table');
        if (empty($rows)) return null;

        $first = (string)$rows[0]->sFName;
        $last  = (string)$rows[0]->sLName;

        $full = trim($first . ' ' . $last);
        return $full !== '' ? $full : null;
    }

    public function sendMoveoutEmail()
    {
        // Get user session data
        $user_data = $this->session->userdata('user_data');

        if (!$user_data || !$user_data['logged_in']) {
            echo json_encode(['success' => false, 'error' => 'User not logged in.']);
            return;
        }

        // Decode the incoming JSON request
        $requestData = json_decode($this->input->raw_input_stream, true);

        if (!isset($requestData['moveoutDate'])) {
            echo json_encode(['success' => false, 'error' => 'Moveout date is required.']);
            return;
        }

        try {
            $this->sendMoveoutConfirmationEmail($user_data, $requestData['moveoutDate']);
            echo json_encode(['success' => true, 'message' => 'Email sent successfully.']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => 'Failed to send email: ' . $e->getMessage()]);
        }
    }
}
