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
        // $dateScheduledOutWithCancel = $requestData['dateScheduledOutWithCancel'] ?? false;
        $dateScheduledOutWithCancel = !empty($requestData['dateScheduledOutWithCancel']) && $requestData['dateScheduledOutWithCancel'] !== 'false';
        $location = $user_data['location'];
        // echo "<pre>";print_r($requestData);die();
        // NEW: Calculate rent due if not cancelling
        // if (!$dateScheduledOutWithCancel) {
            // First check if unit is complimentary
            // print_r($ledgerID);die();
            $isComplimentary = $this->isUnitComplimentaryByLedgerID($ledgerID, $location);

            // echo "<pre>";print_r($isComplimentary);die();

            if ($isComplimentary) {
                
                // Skip payment calculation for complimentary units
                // Proceed directly to schedule moveout
            } else {
                
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
                // echo "<pre>";print_r($rentCalculation);die();
            // }
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
                    <dExpirationDate>2025-12-25</dExpirationDate>
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
            // First check if unit is complimentary
            $isComplimentary = $this->isUnitComplimentaryByLedgerID($ledgerID, $location);
            
            if ($isComplimentary) {
                return [
                    'success' => true,
                    'rent_due' => 0,
                    'total_current_due' => 0,
                    'total_owing' => 0,
                    'paid_through_date' => '',
                    'scheduled_out_date' => $scheduledOutDate,
                    'days_between' => 0,
                    'daily_rate' => 0,
                    'monthly_rate' => 0,
                    'annual_rate' => 0,
                    'message' => 'Unit is complimentary - no payment required.'
                ];
            }

            // Get paid through date
            $paidThroughDate = $this->getPaidThroughDateByLedgerID($location, $ledgerID);
            
            if (!$paidThroughDate) {
                return ['success' => false, 'error' => 'Could not retrieve paid through date.'];
            }

            // Get unit rent rate from database using UnitID
            $unitRentRate = $this->getUnitRentRateByLedgerID($ledgerID, $location);
            // echo "<pre>";print_r($unitRentRate);die();
            if (!$unitRentRate) {
                return ['success' => false, 'error' => 'Could not retrieve unit rent rate.'];
            }

            // NEW: Get total current due amount
            $totalCurrentDue = $this->getTotalCurrentDue($location, $ledgerID);
            // echo "<pre>";print_r($totalCurrentDue);die();
            if ($totalCurrentDue === null) {
                return ['success' => false, 'error' => 'Could not retrieve current due amount.'];
            }

            // Convert dates for calculation
            $paidThrough = DateTime::createFromFormat('d/m/Y', $paidThroughDate);
            $scheduledOut = DateTime::createFromFormat('Y-m-d', $scheduledOutDate);
            
            if (!$paidThrough || !$scheduledOut) {
                return ['success' => false, 'error' => 'Invalid date format.'];
            }

            // Calculate days between paid through and scheduled out (SIGNED)
            // If scheduledOut is on or before paidThrough, no rent is due.
            $daysBetween = (int)$paidThrough->diff($scheduledOut)->format('%r%a');

            if ($daysBetween <= 0) {
                return [
                    'success' => true,
                    'rent_due' => 0,
                    'total_current_due' => $totalCurrentDue,
                    'total_owing' => $totalCurrentDue,
                    'paid_through_date' => $paidThroughDate,
                    'scheduled_out_date' => $scheduledOutDate,
                    'days_between' => 0,
                    'daily_rate' => 0,
                    'monthly_rate' => $unitRentRate,
                    'annual_rate' => $unitRentRate * 12,
                    'message' => 'No rent due - move out date is on or before paid through date.'
                ];
            }

            // Calculate using your exact formula with proper rounding:
            $annualRate = $unitRentRate * 12; // Monthly rate * 12 months
            $dailyRate = $annualRate / 365;   // Annual rate / 365 days
            $rentDue = $daysBetween * $dailyRate; // Days * daily rate
            
            // Calculate total owing (rent due + current due)
            $totalOwing = $rentDue + $totalCurrentDue;

            return [
                'success' => true,
                'rent_due' => round($rentDue, 3), // Round to 3 decimal places
                'total_current_due' => round($totalCurrentDue, 2),
                'total_owing' => round($totalOwing, 2),
                'paid_through_date' => $paidThroughDate,
                'scheduled_out_date' => $scheduledOutDate,
                'days_between' => $daysBetween,
                'monthly_rate' => round($unitRentRate, 3),
                'annual_rate' => round($annualRate, 3),
                'daily_rate' => round($dailyRate, 3),
                'message' => "Rent due: $" . round($rentDue, 3) . " for " . $daysBetween . " days + Current due: $" . round($totalCurrentDue, 2)
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => 'Error calculating rent: ' . $e->getMessage()];
        }
    }

    // NEW: Function to get total current due for a specific ledger
    private function getTotalCurrentDue($location, $ledgerID)
    {
        try {
            // Get user session data to get tenant_id
            $user_data = $this->session->userdata('user_data');
            $tenant_id = $user_data['user_id'];

            // Use the existing SOAP API to get account balance
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

            // Extract balance information and sum ALL balances (like the UI does)
            $balances = $xml->xpath('//diffgr:diffgram/NewDataSet/Table1');
            $totalBalance = 0;

            foreach ($balances as $balance) {
                $balance_amount = (float)$balance->dcBalance;
                $totalBalance += $balance_amount;
            }

            return $totalBalance; // Return total of all balances
            
        } catch (Exception $e) {
            return null;
        }
    }

    // NEW: Function to get unit rent rate from SOAP API using LedgerID
    private function getUnitRentRateByLedgerID($ledgerID, $location)
    {
        // Get tenant_id from session
        $user_data = $this->session->userdata('user_data');
        if (!$user_data || empty($user_data['user_id'])) {
            return null;
        }
        $tenant_id = $user_data['user_id'];

        // Fetch 1-month and 2-month prepay XML
        $xml1 = $this->fetchCustomerAccountsBalanceDetailsWithPrepayment($location, $tenant_id, 1);
        $xml2 = $this->fetchCustomerAccountsBalanceDetailsWithPrepayment($location, $tenant_id, 2);

        $getBalanceFor = function($xmlStr) use ($ledgerID) {
            if (!$xmlStr) return 0.0;

            libxml_use_internal_errors(true);
            $xml = simplexml_load_string($xmlStr);
            if ($xml === false) return 0.0;

            $xml->registerXPathNamespace('soap', 'http://schemas.xmlsoap.org/soap/envelope/');
            $xml->registerXPathNamespace('diffgr', 'urn:schemas-microsoft-com:xml-diffgram-v1');

            $rows = $xml->xpath('//diffgr:diffgram/NewDataSet/Table1');
            if (!$rows) return 0.0;

            $sum = 0.0;
            foreach ($rows as $row) {
                $rowLedgerId = isset($row->LedgerID) ? (int)$row->LedgerID : null;
                $item = isset($row->sItem) ? (string)$row->sItem : '';
                if ($rowLedgerId === (int)$ledgerID && $item === 'Rent') {
                    $sum += isset($row->dcBalance) ? (float)$row->dcBalance : 0.0;
                }
            }
            return $sum;
        };

        $one = $getBalanceFor($xml1);
        $two = $getBalanceFor($xml2);

        return round($two - $one, 2); // e.g., 15.00 - 10.00 = 5.00
    }

private function extractRentBalancesByUnit($xmlString)
{
	$balancesByUnit = [];

	libxml_use_internal_errors(true);
	$xml = simplexml_load_string($xmlString);
	if ($xml === false) {
		return $balancesByUnit;
	}

	$xml->registerXPathNamespace('soap', 'http://schemas.xmlsoap.org/soap/envelope/');
	$xml->registerXPathNamespace('diffgr', 'urn:schemas-microsoft-com:xml-diffgram-v1');

	$error_code = $xml->xpath('//diffgr:diffgram/NewDataSet/RT/Ret_Code');
	if (!empty($error_code) && (int)$error_code[0] < 0) {
		return $balancesByUnit;
	}

	$rows = $xml->xpath('//diffgr:diffgram/NewDataSet/Table1');
	if (!$rows) {
		return $balancesByUnit;
	}

	foreach ($rows as $row) {
		$item = isset($row->sItem) ? (string)$row->sItem : '';
		if ($item !== 'Rent') {
			continue;
		}
		$unitName = isset($row->sUnitName) ? (string)$row->sUnitName : '';
		$dcBalance = isset($row->dcBalance) ? (float)$row->dcBalance : 0.0;

		if (!isset($balancesByUnit[$unitName])) {
			$balancesByUnit[$unitName] = 0.0;
		}
		$balancesByUnit[$unitName] += $dcBalance;
	}

	return $balancesByUnit;
}

/* private function getUnitRentFromPrepayDiff($location, $tenant_id)
{
	// Fetch 1-month and 2-month prepay snapshots
	$oneMonthXml = $this->fetchCustomerAccountsBalanceDetailsWithPrepayment($location, $tenant_id, 1);
	$twoMonthXml = $this->fetchCustomerAccountsBalanceDetailsWithPrepayment($location, $tenant_id, 2);

	$one = $this->extractRentBalancesByUnit($oneMonthXml);
	$two = $this->extractRentBalancesByUnit($twoMonthXml);

	$result = [];
	foreach ($two as $unitName => $twoBal) {
		$oneBal = isset($one[$unitName]) ? $one[$unitName] : 0.0;
		$result[$unitName] = round($twoBal - $oneBal, 2);
	}

	// If any unit is only in 1-month and not in 2-month, ensure it still appears (optional)
	foreach ($one as $unitName => $oneBal) {
		if (!isset($result[$unitName])) {
			$result[$unitName] = round(0.0 - $oneBal, 2);
		}
	}

	return $result; // e.g., ['069' => 5.00, '1190' => 10.00]
} */

    private function fetchCustomerAccountsBalanceDetailsWithPrepayment($location, $tenant_id, $number_of_future_periods = 1)
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
                  <iNumberOfMonthsPrepay>' . (int)$number_of_future_periods . '</iNumberOfMonthsPrepay>
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
        // $mail->addAddress('ronakp.dev@gmail.com');

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
        // echo "updateScheduledOut"; die;
        
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

    // NEW: Function to check if unit is complimentary with debugging
    private function isUnitComplimentaryByLedgerID($ledgerID, $location)
    {
        try {
            if (empty($ledgerID) || empty($location)) {
                return false;
            }

            $user_data = $this->session->userdata('user_data');
            if (!$user_data || empty($user_data['user_id'])) {
                return false;
            }
            $tenant_id = $user_data['user_id'];

            // Resolve UnitID for this ledger from balances (tenant-scoped)
            $response = $this->fetchAllAccountUnits($location, $tenant_id);
            if (!$response) {
                return false;
            }

            libxml_use_internal_errors(true);
            $xml = simplexml_load_string($response);
            if ($xml === false) {
                return false;
            }

            $xml->registerXPathNamespace('soap', 'http://schemas.xmlsoap.org/soap/envelope/');
            $xml->registerXPathNamespace('diffgr', 'urn:schemas-microsoft-com:xml-diffgram-v1');

            $rows = $xml->xpath('//diffgr:diffgram/NewDataSet/Table1');
            if (empty($rows)) {
                return false;
            }

            $unitID = null;
            foreach ($rows as $row) {
                if ((string)$row->LedgerID === (string)$ledgerID) {
                    $unitID = (int)$row->UnitID;
                    break;
                }
            }
            
            if (!$unitID) {
                return false;
            }

            // Use the payment probe method to test if unit is complimentary
            $probe = $this->probeComplimentaryViaPaymentEnhanced($tenant_id, $location, $unitID);
            
            return $probe !== null ? $probe : false;

        } catch (\Throwable $e) {
            return false;
        }
    }

    private function getFutureChargesInternal(array $unitIDs)
    {
        // Build URL to your controller endpoint
        $url = rtrim(site_url('payment/get_future_charges'), '/');
        
        echo "DEBUG: Calling URL: $url<br>";
        echo "DEBUG: UnitIDs: " . json_encode($unitIDs) . "<br>";

        $payload = json_encode([
            'unit_ids'   => array_values($unitIDs),
            'unit_names' => [],  // optional; endpoint ignores or fills it
        ]);
        
        echo "DEBUG: Payload: $payload<br>";

        // Get session cookie to pass authentication
        $sessionCookie = '';
        if (isset($_COOKIE[ini_get('session.name')])) {
            $sessionCookie = ini_get('session.name') . '=' . $_COOKIE[ini_get('session.name')];
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
                'Cookie: ' . $sessionCookie,
            ],
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSL_VERIFYPEER => 0,
        ]);
        
        echo "DEBUG: Session Cookie: $sessionCookie<br>";
        
        $res = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        echo "DEBUG: HTTP Code: $httpCode<br>";
        echo "DEBUG: cURL Error: $error<br>";
        echo "DEBUG: Raw Response: " . htmlspecialchars($res) . "<br>";

        if (!$res) {
            echo "DEBUG: No response received<br>";
            return null;
        }

        $decoded = json_decode($res, true);
        echo "DEBUG: Decoded Response: " . json_encode($decoded) . "<br>";
        
        return is_array($decoded) ? $decoded : null;
    }

    private function probeComplimentaryViaPaymentEnhanced($tenant_id, $location, $unitID)
    {
        // Try small test amount; gateway is authoritative about "no payment due/complimentary"
        $response = $this->soapPaymentProbe($tenant_id, $location, $unitID, '0.01');
        if (!$response) return null;

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($response);
        if ($xml === false) return null;

        $xml->registerXPathNamespace('soap', 'http://schemas.xmlsoap.org/soap/envelope/');
        $xml->registerXPathNamespace('diffgr', 'urn:schemas-microsoft-com:xml-diffgram-v1');

        $codeNodes = $xml->xpath('//diffgr:diffgram/NewDataSet/RT/Ret_Code');
        $msgNodes  = $xml->xpath('//diffgr:diffgram/NewDataSet/RT/Ret_Msg');

        $code = (!empty($codeNodes)) ? (int)$codeNodes[0] : null;
        $msg  = (!empty($msgNodes))  ? strtolower((string)$msgNodes[0]) : '';

        // Success (>=0) → probe accepted → NOT complimentary
        if ($code !== null && $code >= 0) {
            return false;
        }

        // Negative code with known "no charge needed" semantics → complimentary
        $complimentaryHints = [
            'no payment needed',
            'no open charges',
            'no charges due',
            'compliment',         // matches complimentary/complimented
            'zero balance only',
            'cannot take payment for zero',
            'no rent due',
            'already paid through',
            'no current charges',
        ];
        foreach ($complimentaryHints as $hint) {
            if ($msg !== '' && strpos($msg, $hint) !== false) {
                return true;
            }
        }

        // If inconclusive, return null
        return null;
    }

    private function soapPaymentProbe($tenant_id, $location, $unitID, $amount)
    {
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://api.smdservers.net/CCWs_3.5/CallCenterWs.asmx',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => '
            <soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
              <soap:Body>
                <PaymentMultipleWithSource xmlns="http://tempuri.org/CallCenterWs/CallCenterWs">
                  <sCorpCode>CCBZ</sCorpCode>
                  <sLocationCode>' . htmlspecialchars($location, ENT_QUOTES, 'UTF-8') . '</sLocationCode>
                  <sCorpUserName>Ali:::MAMMOTHSW28BGD9OUBBX</sCorpUserName>
                  <sCorpPassword>Currie131!</sCorpPassword>
                  <iTenantID>' . (int)$tenant_id . '</iTenantID>
                  <sUnitIDs>' . (int)$unitID . '</sUnitIDs>
                  <sPaymentAmounts>' . $amount . '</sPaymentAmounts>
                  <iCreditCardType>1</iCreditCardType>
                  <sCreditCardNumber>0000000000000000</sCreditCardNumber>
                  <sCreditCardCVV>000</sCreditCardCVV>
                  <dExpirationDate>2025-12-25</dExpirationDate>
                  <sBillingName>Test</sBillingName>
                  <bTestMode>true</bTestMode>
                  <iSource>10</iSource>
                </PaymentMultipleWithSource>
              </soap:Body>
            </soap:Envelope>',
            CURLOPT_HTTPHEADER => array(
                'SOAPAction: http://tempuri.org/CallCenterWs/CallCenterWs/PaymentMultipleWithSource',
                'Content-Type: text/xml; charset=utf-8',
            ),
        ));
        $r = curl_exec($curl);
        curl_close($curl);
        return $r;
    }

    // Add this method to call the GetFutureCharges SOAP API
    private function getFutureChargesViaSOAP($location, $tenant_id, $unitIDs)
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
                <GetFutureCharges xmlns="http://tempuri.org/CallCenterWs/CallCenterWs">
                  <sCorpCode>CCBZ</sCorpCode>
                  <sLocationCode>' . $location . '</sLocationCode>
                  <sCorpUserName>Ali:::MAMMOTHSW28BGD9OUBBX</sCorpUserName>
                  <sCorpPassword>Currie131!</sCorpPassword>
                  <iTenantID>' . $tenant_id . '</iTenantID>
                  <sUnitIDs>' . implode(',', $unitIDs) . '</sUnitIDs>
                </GetFutureCharges>
              </soap:Body>
            </soap:Envelope>',
            CURLOPT_HTTPHEADER => [
                'SOAPAction: http://tempuri.org/CallCenterWs/CallCenterWs/GetFutureCharges',
                'Content-Type: text/xml; charset=utf-8',
            ],
        ]);
        
        $response = curl_exec($curl);
        curl_close($curl);
        return $response;
    }
}
