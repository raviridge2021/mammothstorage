<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Email_library
{

    public function sendAdminNewCustomerPabblyEmail2($studioId, $customerId, $customerName, $customerEmail, $customerPhone, $zipCode, $artistName, $referenceNumber, $referralSource)
    {
        $this->load->library('GuzzleHttpClient');

        try {
            $request_data = json_encode([
                "studioId" => $studioId,
                "customerId" => $customerId,
                "customerName" => $customerName,
                "customerEmail" => $customerEmail,
                "customerPhone" => $customerPhone,
                "zipCode" => $zipCode,
                "artistName" => $artistName,  // since ArtistName is null
                "referenceNumber" => $referenceNumber,  // since ReferralCode is null
                "referralSource" => $referralSource  // since WhereDidYouHearAboutUs is null
            ]);


            // Set the request headers
            $headers = [
                'Content-Type' => 'application/json'
            ];

            // Make the POST request
            $response = $this->guzzlehttpclient->request('POST', 'https://connect.pabbly.com/workflow/sendwebhookdata/IjU3NjYwNTZjMDYzNjA0M2M1MjY4NTUzMDUxMzAi_pc', [
                'body' => $request_data,
                'headers' => $headers
            ]);

            // Get the response body
            $response_body = $response->getBody()->getContents();

            // Decode the JSON response
            $response_data = json_decode($response_body, true);

            // Check if the response is successful and contains a token
            if (isset($response_data['status']) == "success") {
                return ['success' => true];
            } else {
                return ['success' => false];
            }
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
}
