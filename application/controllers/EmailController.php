<?php
defined('BASEPATH') or exit('No direct script access allowed');

class EmailController extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
    }

    public function sendAdminEmail()
    {
        try {

            // Check if the request is an AJAX request
            if (!$this->input->is_ajax_request()) {
                $this->output
                    ->set_content_type('application/json')
                    ->set_output(json_encode(['error' => 'Invalid request']))
                    ->set_status_header(400);
                return;
            }

            $json = file_get_contents('php://input');
            $data = json_decode($json);


            $name = $data->customerName;
            $email = $data->emailAddress;
            $messageText = $data->messageText;

            // Prepare the data array
            // PHPMailer configuration
            $smtpData = array(
                'smtpHost' => "smtp.gmail.com",  // Updated to match Google Workspace SMTP server
                'smtpPort' => 587,               // Use port 587 with TLS for secure connection
                'smtpUsername' => "10hello@mammothstorage.com.au",
                'smtpPassword' => "ycpl dsjd xtkr hpss",
                'smtpFromEmail' => "10hello@mammothstorage.com.au",
                'smtpFromName' => "Mammoth Storage"
            );

            $messageBody = '
                <html>
                <head>
                    <title>New Customer Notification From Mammoth Storage</title>
                </head>
                <body>
                    <div style="font-family: Arial, sans-serif; margin: 20px;">
                        <p><strong>Customer Name:</strong> ' . $name . '</p>
                        <p><strong>Customer Email:</strong> ' . $email . '</p>
                        <p><strong>Text:</strong> ' . $messageText . '</p>

                        <p style="margin-top: 20px;">© 2024 - Mammoth Storage</p>
                    </div>
                </body>
                </html>
                ';

            // Load PHPMailer library
            $this->load->library('phpmailer_lib');

            // PHPMailer object
            $mail = $this->phpmailer_lib->load();

            // SMTP configuration
            $mail->isSMTP();
            $mail->Host     = $smtpData['smtpHost'];
            $mail->SMTPAuth = true;
            $mail->Username =  $smtpData['smtpUsername'];
            $mail->Password = $smtpData['smtpPassword'];
            $mail->SMTPSecure = 'tls';
            $mail->Port     = $smtpData['smtpPort'];

            $mail->setFrom($smtpData['smtpFromEmail'], $smtpData['smtpFromName']);
            //$mail->addReplyTo('info@example.com', 'CodexWorld');

            // Add a recipient
            $mail->addAddress("10hello@mammothstorage.com.au");
            // Add cc or bcc
            // $mail->addCC('cc@example.com');
            // $mail->addBCC('bcc@example.com');

            // Email subject
            $mail->Subject = 'New Customer Notification From Mammoth Storage';

            // Set email format to HTML
            $mail->isHTML(true);

            // Email body content
            $mailContent = $messageBody;

            $mail->Body = $mailContent;


            // Send email
            if ($mail->send()) {
                // echo 'Message could not be sent.';
                //echo 'Mailer Error: ' . $mail->ErrorInfo;

                $this->output
                    ->set_content_type('application/json')
                    ->set_output(json_encode(['success' => true]))
                    ->set_status_header(200);
            } else {
                $this->output
                    ->set_content_type('application/json')
                    ->set_output(json_encode(['success' => false, 'error' => 'Failed to send email']))
                    ->set_status_header(200);
            }
        } catch (Exception $e) {
            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode(['error' => $e->getMessage()]))
                ->set_status_header(500);
        }
    }


    public function sendSignUpEmail()
    {
        try {

            // Check if the request is an AJAX request
            if (!$this->input->is_ajax_request()) {
                $this->output
                    ->set_content_type('application/json')
                    ->set_output(json_encode(['error' => 'Invalid request']))
                    ->set_status_header(400);
                return;
            }

            $json = file_get_contents('php://input');
            $data = json_decode($json);


            $email = $data->emailAddress;
            $location = $data->location;
            $locationName = $data->locationName;

            // Combine email and location into a single string
            $dataToEncrypt = json_encode(['email' => $email, 'location' => $location, 'location_name' => $locationName]); // Convert to JSON string

            // Encrypt the combined data
            $encryptionKey = 'mammoth_account_reset_password_key'; // Your encryption key
            $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc')); // Generate a secure IV
            $token = openssl_encrypt($dataToEncrypt, 'aes-256-cbc', $encryptionKey, 0, $iv); // Encrypt the combined data

            // Combine the IV with the encrypted token for later use
            $tokenWithIv = base64_encode($iv . '::' . $token); // Store IV with the token

            // Create the reset password URL with the encrypted token
            $resetPasswordUrl = "https://account.mammothstorage.com.au/reset/password?token=" . urlencode($tokenWithIv);


            // Prepare the data array
            // PHPMailer configuration
            $smtpData = array(
                'smtpHost' => "smtp.gmail.com",  // Updated to match Google Workspace SMTP server
                'smtpPort' => 587,               // Use port 587 with TLS for secure connection
                'smtpUsername' => "10hello@mammothstorage.com.au",
                'smtpPassword' => "ycpl dsjd xtkr hpss",
                'smtpFromEmail' => "10hello@mammothstorage.com.au",
                'smtpFromName' => "Mammoth Storage"
            );

            $messageBody = '
        <html>
        <body style="margin: 0; padding: 0; background-color: #f8f9fa; font-family: Arial, sans-serif;">
            <div style="width: 90%; max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1); overflow: hidden;">
                <div style="background-color: #ffffff; padding: 20px; text-align: center;">
                    <img src="cid:logo" alt="Mammoth Storage Logo" style="width: 150px; margin-bottom: 10px;">
                </div>
                <div style="padding: 20px;">
                    <p style="margin: 10px 0; font-size: 16px;">Hello,</p>
                    <p style="margin: 10px 0; font-size: 16px;">Please use the following link to sign up to your Mammoth Account.</p>
                    <p style="margin: 10px 0; text-align: center;">
                        <a href="' . $resetPasswordUrl . '" style="display: inline-block; padding: 10px 20px; margin-bottom: 10px;  margin-top: 10px; background-color: #D02729; color: white; text-decoration: none; border-radius: 5px; font-weight: bold;">Click Here To Complete Sign Up</a>
                    </p>
                    <p style="margin: 10px 0; font-size: 16px;">Thank you,<br>Mammoth Storage</p>
                </div>
                <div style="text-align: center; padding: 10px; background-color: #f1f1f1; font-size: 12px;">
                    &copy; ' . date('Y') . ' Mammoth Storage. All rights reserved.
                </div>
            </div>
        </body>
        </html>
    ';

            // Load PHPMailer library
            $this->load->library('phpmailer_lib');

            // PHPMailer object
            $mail = $this->phpmailer_lib->load();

            // SMTP configuration
            $mail->isSMTP();
            $mail->Host     = $smtpData['smtpHost'];
            $mail->SMTPAuth = true;
            $mail->Username =  $smtpData['smtpUsername'];
            $mail->Password = $smtpData['smtpPassword'];
            $mail->SMTPSecure = 'tls';
            $mail->Port     = $smtpData['smtpPort'];

            $mail->setFrom($smtpData['smtpFromEmail'], $smtpData['smtpFromName']);
            //$mail->addReplyTo('info@example.com', 'CodexWorld');

            // Add a recipient
            $mail->addAddress($email);
            // Add cc or bcc
            // $mail->addCC('cc@example.com');
            // $mail->addBCC('bcc@example.com');

            // Email subject
            $mail->Subject = 'New Sign Up Notification From Mammoth Storage';

            // Set email format to HTML
            $mail->isHTML(true);
            // Add logo as an embedded image
            $mail->AddEmbeddedImage(FCPATH . 'assets/images/Mammoth-Storage.jpg', 'logo'); // Adjust the path as needed

            // Email body content
            $mailContent = $messageBody;

            $mail->Body = $mailContent;


            // Send email
            if ($mail->send()) {
                // echo 'Message could not be sent.';
                //echo 'Mailer Error: ' . $mail->ErrorInfo;

                $this->output
                    ->set_content_type('application/json')
                    ->set_output(json_encode(['success' => true]))
                    ->set_status_header(200);
            } else {
                $this->output
                    ->set_content_type('application/json')
                    ->set_output(json_encode(['success' => false, 'error' => 'Failed to send email']))
                    ->set_status_header(200);
            }
        } catch (Exception $e) {
            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode(['error' => $e->getMessage()]))
                ->set_status_header(500);
        }
    }

    public function sendForgotPasswordEmail()
    {
        try {
            // Check if the request is an AJAX request
            if (!$this->input->is_ajax_request()) {
                $this->output
                    ->set_content_type('application/json')
                    ->set_output(json_encode(['error' => 'Invalid request']))
                    ->set_status_header(400);
                return;
            }

            $json = file_get_contents('php://input');
            $data = json_decode($json);
            $email = $data->emailAddress;
            $location = $data->location;
            $locationName = $data->locationName;

            // Combine email and location into a single string
            $dataToEncrypt = json_encode(['email' => $email, 'location' => $location, 'location_name' => $locationName]); // Convert to JSON string

            // Encrypt the combined data
            $encryptionKey = 'mammoth_account_reset_password_key'; // Your encryption key
            $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc')); // Generate a secure IV
            $token = openssl_encrypt($dataToEncrypt, 'aes-256-cbc', $encryptionKey, 0, $iv); // Encrypt the combined data

            // Combine the IV with the encrypted token for later use
            $tokenWithIv = base64_encode($iv . '::' . $token); // Store IV with the token

            // Create the reset password URL with the encrypted token
            $resetPasswordUrl = "https://account.mammothstorage.com.au/reset/password?token=" . urlencode($tokenWithIv);

            // Prepare the SMTP data array
            $smtpData = array(
                'smtpHost' => "smtp.gmail.com",  // Update to match your SMTP server
                'smtpPort' => 587,
                'smtpUsername' => "10hello@mammothstorage.com.au",
                'smtpPassword' => "ycpl dsjd xtkr hpss",
                'smtpFromEmail' => "10hello@mammothstorage.com.au",
                'smtpFromName' => "Mammoth Storage"
            );

            $messageBody = '
        <html>
        <body style="margin: 0; padding: 0; background-color: #f8f9fa; font-family: Arial, sans-serif;">
            <div style="width: 90%; max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1); overflow: hidden;">
                <div style="background-color: #ffffff; padding: 20px; text-align: center;">
                    <img src="cid:logo" alt="Mammoth Storage Logo" style="width: 150px; margin-bottom: 10px;">
                </div>
                <div style="padding: 20px;">
                    <p style="margin: 10px 0; font-size: 16px;">Hello,</p>
                    <p style="margin: 10px 0; font-size: 16px;">We received a request to reset your password. Please use the link below to create a new password:</p>
                    <p style="margin: 10px 0; text-align: center;">
                        <a href="' . $resetPasswordUrl . '" style="display: inline-block; padding: 10px 20px; margin-bottom: 10px; background-color: #D02729; color: white; text-decoration: none; border-radius: 5px; font-weight: bold;">Reset Password</a>
                    </p>
                    <p style="margin: 10px 0; font-size: 16px;">If you did not request this, please ignore this email.</p>
                    <p style="margin: 10px 0; font-size: 16px;">Thank you,<br>Mammoth Storage</p>
                </div>
                <div style="text-align: center; padding: 10px; background-color: #f1f1f1; font-size: 12px;">
                    &copy; ' . date('Y') . ' Mammoth Storage. All rights reserved.
                </div>
            </div>
        </body>
        </html>
        ';

            // Load PHPMailer library
            $this->load->library('phpmailer_lib');

            // PHPMailer object
            $mail = $this->phpmailer_lib->load();

            // SMTP configuration
            $mail->isSMTP();
            $mail->Host     = $smtpData['smtpHost'];
            $mail->SMTPAuth = true;
            $mail->Username = $smtpData['smtpUsername'];
            $mail->Password = $smtpData['smtpPassword'];
            $mail->SMTPSecure = 'tls';
            $mail->Port     = $smtpData['smtpPort'];

            $mail->setFrom($smtpData['smtpFromEmail'], $smtpData['smtpFromName']);

            // Add a recipient
            $mail->addAddress($email);
           // $mail->addAddress("tobias@mammothstorage.com.au");

            // Email subject
            $mail->Subject = 'Password Reset Request from Mammoth Storage';

            // Set email format to HTML
            $mail->isHTML(true);
            // Add logo as an embedded image
            $mail->AddEmbeddedImage(FCPATH . 'assets/images/Mammoth-Storage.jpg', 'logo'); // Adjust the path as needed
            // Email body content
            $mail->Body = $messageBody;

            // Send email
            if ($mail->send()) {
                $this->output
                    ->set_content_type('application/json')
                    ->set_output(json_encode(['success' => true]))
                    ->set_status_header(200);
            } else {
                $this->output
                    ->set_content_type('application/json')
                    ->set_output(json_encode(['success' => false, 'error' => 'Failed to send email']))
                    ->set_status_header(200);
            }
        } catch (Exception $e) {
            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode(['error' => $e->getMessage()]))
                ->set_status_header(500);
        }
    }
}
