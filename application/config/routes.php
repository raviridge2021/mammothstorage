<?php
defined('BASEPATH') or exit('No direct script access allowed');

/*
| -------------------------------------------------------------------------
| URI ROUTING
| -------------------------------------------------------------------------
| This file lets you re-map URI requests to specific controller functions.
|
| Typically there is a one-to-one relationship between a URL string
| and its corresponding controller class/method. The segments in a
| URL normally follow this pattern:
|
|	example.com/class/method/id/
|
| In some instances, however, you may want to remap this relationship
| so that a different class/function is called than the one
| corresponding to the URL.
|
| Please see the user guide for complete details:
|
|	https://codeigniter.com/userguide3/general/routing.html
|
| -------------------------------------------------------------------------
| RESERVED ROUTES
| -------------------------------------------------------------------------
|
| There are three reserved routes:
|
|	$route['default_controller'] = 'welcome';
|
| This route indicates which controller class should be loaded if the
| URI contains no data. In the above example, the "welcome" class
| would be loaded.
|
|	$route['404_override'] = 'errors/page_missing';
|
| This route will tell the Router which controller/method to use if those
| provided in the URL cannot be matched to a valid route.
|
|	$route['translate_uri_dashes'] = FALSE;
|
| This is not exactly a route, but allows you to automatically route
| controller and method names that contain dashes. '-' isn't a valid
| class or method name character, so it requires translation.
| When you set this option to TRUE, it will replace ALL dashes in the
| controller and method URI segments.
|
| Examples:	my-controller/index	-> my_controller/index
|		my-controller/my-method	-> my_controller/my_method
*/
$route['default_controller'] = 'ProfileController/index'; // Make UnitController the default controller
$route['404_override'] = '';
$route['translate_uri_dashes'] = FALSE;

$route['units'] = 'UnitController/index'; // Route to load the form
$route['units/fetch'] = 'UnitController/fetchUnits'; // Clean and professional route to fetch units via AJAX

$route['profile'] = 'ProfileController/index';
$route['profile/get_tenant_info'] = 'ProfileController/getTenantInfoById';

$route['email/send'] = 'EmailController/sendAdminEmail';
$route['email/signup'] = 'EmailController/sendSignUpEmail';
$route['email/forgot_password'] = 'EmailController/sendForgotPasswordEmail';


$route['invoice/get_invoices'] = 'InvoiceController/getInvoiceHistory';
$route['payment/get_payments'] = 'PaymentController/getPaymentHistory';
$route['payment/update_auto_payment'] = 'PaymentController/updateAutoPayment';
$route['payment/get_payment_types'] = 'PaymentController/getPaymentTypes';
$route['payment/make_payment'] = 'PaymentController/makePayment';
$route['account/get_balance'] = 'AccountBalanceController/getAccountBalance';
$route['units/get_units'] = 'UnitController/getAllUnits';

$route['schedule/moveout'] = 'MoveoutController/scheduleMoveout';

// Custom route for making future charges
$route['payment/get_future_charges'] = 'CustomerAccountsController/getFutureCharges';


$route['login'] = 'LoginController/index';
$route['reset/password'] = 'LoginController/viewResetPasswordView';
$route['reset/update_Password'] = 'LoginController/updateAccountPassword';

$route['login/authenticate'] = 'LoginController/authenticate';
$route['logout'] = 'LoginController/logout';
