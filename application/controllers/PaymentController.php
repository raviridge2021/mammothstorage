<?php
defined('BASEPATH') or exit('No direct script access allowed');

class PaymentController extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
    }
    public function getPaymentHistory()
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

        // Step 1: Fetch tenant's ledgers
        $ledgerResponse = $this->fetchTenantLedgers($location, $tenant_id);

        if (!$ledgerResponse) {
            echo json_encode(['data' => [], 'error' => 'Failed to fetch ledgers.']);
            return;
        }

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($ledgerResponse);

        if ($xml === false) {
            echo json_encode(['data' => [], 'error' => 'Failed to parse the ledger data.']);
            return;
        }

        $xml->registerXPathNamespace('soap', 'http://schemas.xmlsoap.org/soap/envelope/');
        $xml->registerXPathNamespace('diffgr', 'urn:schemas-microsoft-com:xml-diffgram-v1');

        // Handle any errors in the XML response
        $error_code = $xml->xpath('//diffgr:diffgram/NewDataSet/RT/Ret_Code');
        $error_msg = $xml->xpath('//diffgr:diffgram/NewDataSet/RT/Ret_Msg');

        if (!empty($error_code) && (int)$error_code[0] < 0) {
            echo json_encode(['success' => false, 'error' => (string)$error_msg[0]]);
            return;
        }

        // Step 2: Iterate through each ledger and fetch payment history
        $ledgers = $xml->xpath('//diffgr:diffgram/NewDataSet/Ledgers');
        $payment_data = [];

        foreach ($ledgers as $ledger) {
            $ledger_id = (string)$ledger->LedgerID;
            $unit_name = (string)$ledger->sUnitName;

            // Fetch payments for each ledger
            $paymentResponse = $this->fetchTenantPayments($location, $ledger_id);

            if (!$paymentResponse) {
                echo json_encode(['data' => [], 'error' => 'Failed to fetch payment history for ledger ' . $ledger_id]);
                return;
            }

            $xmlPayment = simplexml_load_string($paymentResponse);

            if ($xmlPayment === false) {
                echo json_encode(['data' => [], 'error' => 'Failed to parse the payment data.']);
                return;
            }

            $xmlPayment->registerXPathNamespace('soap', 'http://schemas.xmlsoap.org/soap/envelope/');
            $xmlPayment->registerXPathNamespace('diffgr', 'urn:schemas-microsoft-com:xml-diffgram-v1');

            // Step 3: Extract payment history and map data
            $payments = $xmlPayment->xpath('//diffgr:diffgram/NewDataSet/PmtHistory');

            foreach ($payments as $payment) {
                $payment_data[] = [
                    'receipt_id' => (string)$payment->Receipt,
                    'unit_name' => $unit_name,
                    'payment_date' => date('d-m-Y H:i', strtotime((string)$payment->dPmt)),
                    'description' => (string)$payment->Description,
                    'payment_amount' => number_format((float)$payment->Payment, 2),

                ];
            }
        }

        // Apply search filter
        if (!empty($search_value)) {
            $payment_data = array_filter($payment_data, function ($payment) use ($search_value) {
                return stripos($payment['receipt_id'], $search_value) !== false ||
                    stripos($payment['unit_name'], $search_value) !== false;
            });
        }


        $columns = [
            0 => 'receipt_id',
            1 => 'unit_name',
            2 => 'payment_date',
            3 => 'description',
            4 => 'payment_amount'
        ];

        // Apply sorting
        usort($payment_data, function ($a, $b) use ($columns, $order_column, $order_dir) {
            $column = $columns[$order_column];
            if ($order_dir === 'asc') {
                return strcmp($a[$column], $b[$column]);
            }
            return strcmp($b[$column], $a[$column]);
        });

        // Pagination: Extract the relevant slice of data
        $total_records = count($payment_data);
        $payment_data = array_slice($payment_data, $start, $length);

        // Step 4: Return data in DataTables format
        echo json_encode([
            'draw' => intval($draw),
            'recordsTotal' => $total_records,
            'recordsFiltered' => $total_records,
            'data' => array_values($payment_data)  // Re-indexed for DataTables
        ]);
    }


    // Function to fetch tenant invoices
    private function fetchTenantPayments($location, $ledger_id)
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
                <PaymentsByLedgerID xmlns="http://tempuri.org/CallCenterWs/CallCenterWs">
                  <sCorpCode>CCBZ</sCorpCode>
                   <sLocationCode>' . $location . '</sLocationCode>
                  <sCorpUserName>Ali:::MAMMOTHSW28BGD9OUBBX</sCorpUserName>
                  <sCorpPassword>Currie131!</sCorpPassword>
                  <sLedgerID>' . $ledger_id . '</sLedgerID>
                </PaymentsByLedgerID>
              </soap:Body>
            </soap:Envelope>',
            CURLOPT_HTTPHEADER => array(
                'SOAPAction: http://tempuri.org/CallCenterWs/CallCenterWs/PaymentsByLedgerID',
                'Content-Type: text/xml; charset=utf-8',
            ),
        ));

        $response = curl_exec($curl);
        curl_close($curl);
        return $response;
    }



    private function fetchTenantLedgers($location, $tenant_id)
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
                <LedgersByTenantID xmlns="http://tempuri.org/CallCenterWs/CallCenterWs">
                  <sCorpCode>CCBZ</sCorpCode>
                   <sLocationCode>' . $location . '</sLocationCode>
                  <sCorpUserName>Ali:::MAMMOTHSW28BGD9OUBBX</sCorpUserName>
                  <sCorpPassword>Currie131!</sCorpPassword>
                    <sTenantID>' . $tenant_id . '</sTenantID>
                </LedgersByTenantID>
              </soap:Body>
            </soap:Envelope>',
            CURLOPT_HTTPHEADER => array(
                'SOAPAction: http://tempuri.org/CallCenterWs/CallCenterWs/LedgersByTenantID',
                'Content-Type: text/xml; charset=utf-8',
            ),
        ));

        $response = curl_exec($curl);
        curl_close($curl);
        return $response;
    }

    public function getPaymentTypes()
    {
        // Get user session data (if needed), or skip if it's not relevant for this API
        $user_data = $this->session->userdata('user_data');

        // If the user is not logged in, return an error (optional if required)
        if (!$user_data || !$user_data['logged_in']) {
            echo json_encode(['data' => [], 'error' => 'User not logged in.']);
            return;
        }

        $location = $user_data['location'];

        // Fetch payment types via SOAP request
        $response = $this->fetchPaymentTypes($location);

        if (!$response) {
            echo json_encode(['data' => [], 'error' => 'Failed to fetch payment types.']);
            return;
        }

        // Handle XML parsing errors
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($response);

        if ($xml === false) {
            echo json_encode(['data' => [], 'error' => 'Failed to parse the payment types data.']);
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

        // Extract payment types
        $payment_types = $xml->xpath('//diffgr:diffgram/NewDataSet/Table');

        $payment_data = [];

        foreach ($payment_types as $payment_type) {
            $payment_data[] = [
                'PmtTypeID' => (int) $payment_type->PmtTypeID,
                'sPmtTypeDesc' => (string) $payment_type->sPmtTypeDesc,
                'sCategory' => (string) $payment_type->sCategory,
            ];
        }

        // Return the payment types in JSON format
        echo json_encode([
            'success' => true,
            'payment_types' => $payment_data
        ]);
    }
    // Function to fetch payment types
    private function fetchPaymentTypes($location)
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
             <PaymentTypesRetrieve xmlns="http://tempuri.org/CallCenterWs/CallCenterWs">
               <sCorpCode>CCBZ</sCorpCode>
               <sLocationCode>' . $location . '</sLocationCode>
               <sCorpUserName>Ali:::MAMMOTHSW28BGD9OUBBX</sCorpUserName>
               <sCorpPassword>Currie131!</sCorpPassword>
             </PaymentTypesRetrieve>
           </soap:Body>
         </soap:Envelope>',
            CURLOPT_HTTPHEADER => array(
                'SOAPAction: http://tempuri.org/CallCenterWs/CallCenterWs/PaymentTypesRetrieve',
                'Content-Type: text/xml; charset=utf-8',
            ),
        ));

        $response = curl_exec($curl);
        curl_close($curl);
        return $response;
    }


    // Function to handle auto-payment update
    public function updateAutoPayment()
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
            !isset($requestData['ledgerID']) || !isset($requestData['creditCardTypeID']) || !isset($requestData['creditCardNum']) ||
            !isset($requestData['creditCardExpire']) || !isset($requestData['creditCardHolderName']) || !isset($requestData['autoBillType'])
        ) {
            echo json_encode(['success' => false, 'error' => 'All required fields must be provided.']);
            return;
        }

        $ledgerID = $requestData['ledgerID'];
        $creditCardTypeID = $requestData['creditCardTypeID'];
        $creditCardNum = $requestData['creditCardNum'];
        $creditCardExpire = $requestData['creditCardExpire'];
        $creditCardHolderName = $requestData['creditCardHolderName'];
        $autoBillType = $requestData['autoBillType'];
        $location = $user_data['location'];

        // Prepare the SOAP request
        $response = $this->updateBillingInfo($location, $ledgerID, $creditCardTypeID, $creditCardNum, $creditCardExpire, $creditCardHolderName, $autoBillType);

        if (!$response) {
            echo json_encode(['success' => false, 'error' => 'Failed to update payment settings.']);
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
        echo json_encode(['success' => true, 'message' => 'Payment settings updated successfully.']);
    }

    // Function to make the SOAP request for updating billing info
    private function updateBillingInfo($location, $ledgerID, $creditCardTypeID, $creditCardNum, $creditCardExpire, $creditCardHolderName, $autoBillType)
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
                <TenantBillingInfoUpdate xmlns="http://tempuri.org/CallCenterWs/CallCenterWs">
                  <sCorpCode>CCBZ</sCorpCode>
                  <sLocationCode>' . $location . '</sLocationCode>
                  <sCorpUserName>Ali:::MAMMOTHSW28BGD9OUBBX</sCorpUserName>
                  <sCorpPassword>Currie131!</sCorpPassword>
                  <iLedgerID>' . $ledgerID . '</iLedgerID>
                  <iCreditCardTypeID>' . $creditCardTypeID . '</iCreditCardTypeID>
                  <sCreditCardNum>' . $creditCardNum . '</sCreditCardNum>
                  <dCredtiCardExpir>' . $creditCardExpire . '</dCredtiCardExpir>
                  <sCreditCardHolderName>' . $creditCardHolderName . '</sCreditCardHolderName>
                  <iAutoBillType>' . $autoBillType . '</iAutoBillType>
                </TenantBillingInfoUpdate>
              </soap:Body>
            </soap:Envelope>',
            CURLOPT_HTTPHEADER => array(
                'SOAPAction: http://tempuri.org/CallCenterWs/CallCenterWs/TenantBillingInfoUpdate',
                'Content-Type: text/xml; charset=utf-8',
            ),
        ));

        $response = curl_exec($curl);
        curl_close($curl);
        return $response;
    }



    public function makePayment()
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
            !isset($requestData['creditCardTypeID']) || !isset($requestData['creditCardNum']) ||
            !isset($requestData['creditCardExpire']) || !isset($requestData['creditCardHolderName']) || !isset($requestData['creditCardCVV']) || !isset($requestData['paymentOptions']) || !isset($requestData['sUnitIDs']) || !isset($requestData['sPaymentAmounts'])
        ) {
            echo json_encode(['success' => false, 'error' => 'All required fields must be provided.']);
            return;
        }

        $creditCardTypeID = $requestData['creditCardTypeID'];
        $creditCardNum = $requestData['creditCardNum'];
        $creditCardExpire = $requestData['creditCardExpire'];
        $creditCardHolderName = $requestData['creditCardHolderName'];
        $creditCardCVV = $requestData['creditCardCVV'];
        $paymentOptions = $requestData['paymentOptions'];
        $sUnitIDs = $requestData['sUnitIDs'];
        $sPaymentAmounts = $requestData['sPaymentAmounts'];

        $tenant_id = $user_data['user_id'];
        $location = $user_data['location'];

        if ($paymentOptions == 1) {
            // echo "1"; die;
            $response = $this->multipleSourcePayment($tenant_id, $location, $creditCardTypeID, $creditCardNum, $creditCardExpire, $creditCardHolderName, $creditCardCVV, $sUnitIDs, $sPaymentAmounts);
        } else if ($paymentOptions == 2) {
            // echo "2"; die;
            $number_of_future_periods = $requestData['numberOfMonths'];
            $response2 = $this->makeFutureAllUnitsCharges($location, $tenant_id, $number_of_future_periods);


            // Prepare the SOAP request

            if (!$response2) {
                echo json_encode(['success' => false, 'error' => 'Failed to make future charges.']);
                return;
            }

            // Parse the XML response
            libxml_use_internal_errors(true);
            $xml = simplexml_load_string($response2);

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

            $response = $this->multipleSourcePayment($tenant_id, $location, $creditCardTypeID, $creditCardNum, $creditCardExpire, $creditCardHolderName, $creditCardCVV, $sUnitIDs, $sPaymentAmounts);
        } else {

            echo json_encode(['success' => false, 'error' => 'Please select a payment option to proceed. You can choose to pay the current due, the next month, or multiple months in advance.']);
            return;
        }
        // Prepare the SOAP request

        if (!$response) {
            echo json_encode(['success' => false, 'error' => 'Failed to update payment settings.']);
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
        echo json_encode(['success' => true, 'message' => 'Payment settings updated successfully.']);
    }

    private function multipleSourcePayment($tenant_id, $location, $creditCardTypeID, $creditCardNum, $creditCardExpire, $creditCardHolderName, $creditCardCVV, $sUnitIDs, $sPaymentAmounts)
    {
        $curl = curl_init();
        echo "<pre>"; print_r($sUnitIDs); die;
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
                <PaymentMultipleWithSource xmlns="http://tempuri.org/CallCenterWs/CallCenterWs">
                    <sCorpCode>CCBZ</sCorpCode>
                    <sLocationCode>' . $location . '</sLocationCode>
                    <sCorpUserName>Ali:::MAMMOTHSW28BGD9OUBBX</sCorpUserName>
                    <sCorpPassword>Currie131!</sCorpPassword>
                    <iTenantID>' . $tenant_id . '</iTenantID>
                    <sUnitIDs>' . $sUnitIDs . '</sUnitIDs>
                    <sPaymentAmounts>' . $sPaymentAmounts . '</sPaymentAmounts>
                    <iCreditCardType>' . $creditCardTypeID . '</iCreditCardType>
                    <sCreditCardNumber>' . $creditCardNum . '</sCreditCardNumber>
                    <sCreditCardCVV>' . $creditCardCVV . '</sCreditCardCVV>
                    <dExpirationDate>' . $creditCardExpire . '</dExpirationDate>
                    <sBillingName>' . $creditCardHolderName . '</sBillingName>
                    <bTestMode>false</bTestMode>
                    <iSource>10</iSource>
                </PaymentMultipleWithSource>
              </soap:Body>
            </soap:Envelope>',
            CURLOPT_HTTPHEADER => array(
                'SOAPAction: http://tempuri.org/CallCenterWs/CallCenterWs/PaymentMultipleWithSource',
                'Content-Type: text/xml; charset=utf-8',
            ),
        ));

        $response = curl_exec($curl);
        curl_close($curl);
        return $response;
    }

    private function makeFutureAllUnitsCharges($location, $tenant_id, $number_of_future_periods)
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
                    <CustomerAccountsMakeFutureCharges xmlns="http://tempuri.org/CallCenterWs/CallCenterWs">
                      <sCorpCode>CCBZ</sCorpCode>
                      <sLocationCode>' . $location . '</sLocationCode>
                      <sCorpUserName>Ali:::MAMMOTHSW28BGD9OUBBX</sCorpUserName>
                      <sCorpPassword>Currie131!</sCorpPassword>
                      <iTenantID>' . $tenant_id . '</iTenantID>
                      <iNumberOfFuturePeriods>' . $number_of_future_periods . '</iNumberOfFuturePeriods>
                    </CustomerAccountsMakeFutureCharges>
                  </soap:Body>
                </soap:Envelope>',
            CURLOPT_HTTPHEADER => array(
                'SOAPAction: http://tempuri.org/CallCenterWs/CallCenterWs/CustomerAccountsMakeFutureCharges',
                'Content-Type: text/xml; charset=utf-8',
            ),
        ));

        $response = curl_exec($curl);
        curl_close($curl);
        return $response;
    }
}
