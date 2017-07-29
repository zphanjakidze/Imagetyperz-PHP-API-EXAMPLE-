============================================================================================
PHP API
============================================================================================
You can use the API by importing the imagetypersapi.php file and by using the ImagetypersAPI
class.
============================================================================================
Below you have some examples on how to use the API library (taken from index.php)
--------------------------------------------------------------------------------------------
require('imagetypersapi.php');      // load API library

$USERNAME = "testusername";
$PASSWORD = "************";
$i = new ImagetypersAPI($USERNAME, $PASSWORD);      // init API lib obj

// check account balance
// --------------------------
$balance = $i->account_balance();       // get balance
echo 'Balance: ' . $balance  . '<br>';

// solve normal captcha
// ------------------------------------------------------------------
$captcha_text = $i->solve_captcha('captcha.jpg', TRUE);             // solve captcha, case sensitive
echo 'Captcha text: ' . $captcha_text . '<br>';

// solve recaptcha
// --------------------------------------------------------------------
// check: http://www.imagetyperz.com/Forms/recaptchaapi.aspx on how to get page_url and googlekey
$page_url = 'https://imagetyperz.com/wp-login.php';
$sitekey = '6LdlvgUTAAAAANXvG6Oo0odap_L3oZ29fRZ3LEbr';
$captcha_id = $i->submit_recaptcha($page_url, $sitekey);

//echo 'Waiting for recaptcha to be solved ...';
// check every 10 seconds if recaptcha was solved
while($i->in_progress($captcha_id))     // while still in progress
{
	sleep(10);
}
// completed at this point
$recaptcha_response = $i->retrieve_recaptcha($captcha_id);
echo 'Recaptcha response: ' . $recaptcha_response . '<br>';

// Other examples
// -----------------
// $i = new ImagetypersAPI($USERNAME, $PASSWORD, 120);      // use timeout
// $i = new ImagetypersAPI($USERNAME, $PASSWORD, 120, 5);   // use timeout and reference id

// submit recaptcha with proxy from which it will be solved
// $captcha_id = $i->submit_recaptcha($page_url, $sitekey, "127.0.0.1:1234", "HTTP");

// echo $i->set_captcha_bad($captcha_id);       // set captcha bad

// getters
// echo $i->captcha_id();              // get last captcha id
// echo $i->captcha_text();            // get last captcha text

// echo $i->recaptcha_id();            // get last recaptcha id
// echo $i->recaptcha_response();      // get last recaptcha response

// echo $i->error();                   // get last error   
======================================================================================================
[*] Requires PHP installed