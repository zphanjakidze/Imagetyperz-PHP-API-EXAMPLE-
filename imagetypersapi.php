<?php

// constants
define('CAPTCHA_ENDPOINT', 'http://captchatypers.com/Forms/UploadFileAndGetTextNEW.ashx');
define('RECAPTCHA_SUBMIT_ENDPOINT', 'http://captchatypers.com/captchaapi/UploadRecaptchaV1.ashx');
define('RECAPTCHA_RETRIEVE_ENDPOINT', 'http://captchatypers.com/captchaapi/GetRecaptchaText.ashx');
define('BALANCE_ENDPOINT', 'http://captchatypers.com/Forms/RequestBalance.ashx');
define('BAD_IMAGE_ENDPOINT', 'http://captchatypers.com/Forms/SetBadImage.ashx');

define('USER_AGENT', 'phpAPI1.0');

// Captcha class
class Captcha {

    private $_captcha_id = '';
    private $_text = '';

    function __construct($response) {
        $a = explode('|', $response);       // split response
        if (sizeof($a) < 2) {                  // check if right length
            throw new Exception("cannot parse response from server: " . $response);
        }
        $this->_captcha_id = $a[0];
        $this->_text = join('|', array_slice($a, 1, sizeof($a)));
    }

    // Get captcha ID
    function captcha_id() {
        return $this->_captcha_id;
    }

    // Get captcha text
    function text() {
        return $this->_text;
    }

}

// Recaptcha class
class Recaptcha {

    private $_captcha_id = '';
    private $_response = '';

    function __construct($captcha_id) {
        $this->_captcha_id = $captcha_id;        // set captcha ID on obj
    }

    // Set response
    function set_response($response) {
        $this->_response = $response;
    }

    // Get captcha ID
    function captcha_id() {
        return $this->_captcha_id;
    }

    // Get response
    function response() {
        return $this->_response;
    }

}

// Utils class
class Utils {

    // Make post request
    public static function post($url, $params, $user_agent, $timeout) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_USERAGENT, $user_agent);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        $results = curl_exec($ch);
        curl_close($ch);

        return trim($results);
    }

    // Read file
    public static function read_file($file_path) {
        // check if file exists
        if (!file_exists($file_path)) {
            throw new Exception("captcha file does not exist: " . $file_path);
        }
        $fp = fopen($file_path, "rb");      // open file
        if (!$fp)
            throw new Exception("cannot read captcha file: " . $file_path);
        $file_size = filesize($file_path);      // get file size

        if ($file_size <= 0)        // check it's length (if OK)
            throw new Exception("cannot read captcha file: " . $file_path);

        $data = fread($fp, $file_size);     // read file
        fclose($fp);                        // close file

        $b64_data = base64_encode($data);   // encode it to base64
        return $b64_data;                   // return it
    }

}

class ImagetypersAPI {

    private $_username;
    private $_password;
    private $_timeout;
    private $_ref_id;
    private $_captcha = null;
    private $_recaptcha = null;
    private $_error = '';

    function __construct($username, $password, $timeout = 120, $ref_id = 0) {
        $this->_username = $username;
        $this->_password = $password;
        $this->_timeout = $timeout;
        $this->_ref_id = $ref_id;
    }

    // Solve captcha
    function solve_captcha($captcha_file, $case_sensitive = FALSE) {
        $file_b64 = Utils::read_file($captcha_file);        // read file and b64 encode
        $data = array(
            "action" => "UPLOADCAPTCHA",
            "username" => $this->_username,
            "password" => $this->_password,
            "chkCase" => (int) $case_sensitive,
            "refid" => $this->_ref_id,
            "file" => $file_b64
        );
        $response = Utils::post(CAPTCHA_ENDPOINT, $data, USER_AGENT, $this->_timeout);
        // if file is sent as b64, uploading file ... is in response too, remove it
        $response = str_replace("Uploading file...", "", $response);
        if (strpos($response, 'ERROR:') !== false) {
            $response_err = trim(explode('ERROR:', $response)[1]);
            $this->_error = $response_err;
            throw new Exception($response_err);
        }
        // we have a good response here
        // save captcha to obj and return solved text
        $this->_captcha = new Captcha($response);
        return $this->_captcha->text();     // return captcha text
    }

    // Submit recaptcha
    function submit_recaptcha($page_url, $sitekey, $proxy = '', $proxy_type = '') {
        $data = array(
            "action" => "UPLOADCAPTCHA",
            "username" => $this->_username,
            "password" => $this->_password,
            "pageurl" => $page_url,
            "googlekey" => $sitekey,
            "refid" => $this->_ref_id
        );

        // check for proxy
        if (isset($proxy)) {
            if (!isset($proxy_type)) {
                throw new Exception('proxy set but proxy_type not');
            }
            // we have a good proxy here (at least both params supplied)
            // set it to the data/params
            $data["proxy"] = $proxy;
            $data["proxytype"] = $proxy_type;
        }

        $response = Utils::post(RECAPTCHA_SUBMIT_ENDPOINT, $data, USER_AGENT, $this->_timeout);
        if (strpos($response, 'ERROR:') !== false) {
            $response_err = trim(explode('ERROR:', $response)[1]);
            $this->_error = $response_err;
            throw new Exception($response_err);
        }
        // we have a good response here
        // save captcha to obj and return solved text
        $this->_recaptcha = new Recaptcha($response);
        return $this->_recaptcha->captcha_id();     // return captcha text
    }

    // Get recaptcha response using captcha ID
    function retrieve_recaptcha($captcha_id) {
        $data = array(
            "action" => "GETTEXT",
            "username" => $this->_username,
            "password" => $this->_password,
            "captchaid" => $captcha_id,
            "refid" => $this->_ref_id
        );

        $response = Utils::post(RECAPTCHA_RETRIEVE_ENDPOINT, $data, USER_AGENT, $this->_timeout);
        if (strpos($response, 'ERROR:') !== false) {
            $response_err = trim(explode('ERROR:', $response)[1]);
            // save it to obj error only if it's not, NOT_DECODED
            if (strpos($response_err, 'NOT_DECODED') !== false) {
                $this->_error = $response_err;
            }
            throw new Exception($response_err);
        }

        // set them to obj
        $this->_recaptcha = new Recaptcha($captcha_id);  // remake obj (in case submit wasn't used)
        $this->_recaptcha->set_response($response);      // set recaptcha response
        return $this->_recaptcha->response();            // return response
    }

    // Check if captcha is still in progress
    function in_progress($captcha_id) {
        try {
            $this->retrieve_recaptcha($captcha_id);     // retrieve captcha
            return FALSE;                               // not in progress anymore
        } catch (Exception $ex) {
            if (strpos($ex->getMessage(), 'NOT_DECODED') !== false) {
                return TRUE;                            // still "decoding" it
            }
        }
    }

    // Get account balance
    function account_balance() {
        $data = array(
            "action" => "REQUESTBALANCE",
            "username" => $this->_username,
            "password" => $this->_password,
            "submit" => "Submit"
        );
        $response = Utils::post(BALANCE_ENDPOINT, $data, USER_AGENT, $this->_timeout);
        if (strpos($response, 'ERROR:') !== false) {
            $response_err = trim(explode('ERROR:', $response)[1]);
            $this->_error = $response_err;
            throw new Exception($response_err);
        }

        return '$' . $response;     // return response
    }

    // Set captcha bad
    function set_captcha_bad($captcha_id) {
        // set data array
        $data = array(
            "action" => "SETBADIMAGE",
            "username" => $this->_username,
            "password" => $this->_password,
            "imageid" => $captcha_id,
            "submit" => "Submissssst"
        );
        // do request
        $response = Utils::post(BAD_IMAGE_ENDPOINT, $data, USER_AGENT, $this->_timeout);
        // parse response
        if (strpos($response, 'ERROR:') !== false) {
            $response_err = trim(explode('ERROR:', $response)[1]);
            $this->_error = $response_err;
            throw new Exception($response_err);
        }

        return $response;     // return response
    }

    // Get last captcha text
    function captcha_text() {
        if (is_null($this->_captcha)) {
            return "";
        }
        return $this->_captcha->text();
    }

    // Get last captcha ID
    function captcha_id() {
        if (is_null($this->_captcha)) {
            return "";
        }
        return $this->_captcha->captcha_id();
    }

    // Get last recaptcha ID
    function recaptcha_id() {
        if (is_null($this->_recaptcha)) {
            return "";
        }
        return $this->_recaptcha->captcha_id();
    }

    // Get last recaptcha response
    function recaptcha_response() {
        if (is_null($this->_recaptcha)) {
            return "";
        }
        return $this->_recaptcha->response();
    }

    // Return last error
    function error() {
        return $this->_error;
    }

}

?>