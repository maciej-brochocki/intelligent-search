<?php

//namespace LD\API;
//class Exception extends \Exception {}

class LdConnect
{
    /**
     * user name
     * @var string 
     */
    private $login;
    
    /**
     * MD5 encoded user password
     * @var string 
     */
    private $password;
    
    /**
     * Token is MD5 encoded string, to generate new token use logIn() function
     * @var string 
     */
    public $token = '';

    public $apiUrl = 'api.fidolabs.org/v1/';

    /**
     * set useCallback=TRUE when using callback-API
     * @var boolean 
     */
    public $useCallback;

    /**
     * enter text here, or use setText() function
     * @var string text (lang=en, UTF-8 , max strlen=2000) 
     */
    public $text;
    
    /**
     * copy of result (LD output)
     * @var type 
     */
    public $result_;

    public function __construct($login, $password, $useCallback=false) {
        $this->login = $login;
        $this->password = $password;
        $this->useCallback = $useCallback;
    }

    public function hasCallback($value)
    {
        if (is_bool($value)) {
            $this->useCallback = $value;
        }
        else {
            $this->useCallback = false;
        }
    }

    /**
     * Authorization method, generates token and returns true on success.
     * 
     * @return boolean
     */
    public function logIn()
    {
        if (empty($this->login) || empty($this->password)) {
            throw new Exception('Authorization error: login or password not set');
        }

        try {
            $post_ = array(
                'login' => $this->login,
                'passwd' => $this->password,
                'method' => 'getToken'
            );
            $json = $this->apiQuery($post_, true);
            $token = json_decode($json);
            if (!$token) {
                throw new Exception('JSON decode error in getToken');
            }
            if ((!isset($token->Result) || empty($token->Result))) {
                if (isset($token->Error) && !empty($token->Error)) {
                    throw new Exception($token->Error->desc,$token->Error->code);
                }
                throw new Exception('Authorization error: getToken failed!');
            }
        }
        catch (Exception $ex) {
            throw $ex;
        }
        
        $this->token = md5($this->password . $token->Result);
        return true;
    }

    /**
     * getUserInfo - returns user account details: type, limitations, request count etc.
     * 
     * @return string/array
     */
    public function getUserInfo($format='json')
    {
        if (empty($this->login) || empty($this->password)) {
            throw new Exception('Authorization error: login or password not set');
        }

        try {
            $post_ = array(
                'login' => $this->login,
                'passwd' => $this->password,
                'method' => 'getUserInfo'
            );
            $json = $this->apiQuery($post_, true);
            $output = json_decode($json);
            if (!$output) {
                throw new Exception('JSON decode error in getUserInfo');
            }
            if ((!isset($output->Result) || empty($output->Result))) {
                if (isset($output->Error) && !empty($output->Error)) {
                    throw new Exception($output->Error->desc,$output->Error->code);
                }
                throw new Exception('Authorization error: getUserInfo failed!');
            }
            
        }
        catch (Exception $ex) {
            throw $ex;
        }
        
	switch ($format) {
            case 'json':
                return $json;
            case 'array':
                return json_decode($json,true);
            case 'object':
                return $output;
	}

    }

    /**
     * setup text to parse
     * @param string $text (lang=en, UTF-8 , max strlen=2000) 
     */
    public function setText($text)
    {
        if (strlen($text)<2) {
            throw new Exception('Text not set or invalid. UTF-8 only supported! Check your input data.');
        }
        $this->text = $text;
    }

    /**
     * Sends text to LanguageDecoder using getSimplifiedGraph method,
     * default returned data format is JSON,
     * set $format = 'object' to get objects array,
     * set $format = 'array' to get  associative array,
     * 
     * @param string $format returned data format: 'json'|'object'|'array'
     * @return string/array default=JSON
     * @throws Exception
     */
    public function getSimplifiedGraph($format = 'json')
    {
        try{
            if (strlen($this->text)<2) {
                throw new Exception('You need to set text before sending request!');
            }
            $post_ = array(
                'data'   => $this->text,
                'token'  => $this->token,
                'method' => 'getSimplifiedGraph'
            );
        
            $result = $this->apiQuery($post_);
            if ($format === 'object') {
                $result = json_decode($result);
            }
            elseif ($format === 'array') {
                $result = json_decode($result, true);
            }
            $this->result_ = $result;
        }
        catch (Exception $ex) {
            throw $ex;
        }

        return $result;
    }

    /**
     * Sends text to LanguageDecoder using getGraph method,
     * default returned data format is JSON,
     * set $format = 'object' to get objects array,
     * set $format = 'array' to get  associative array,
     * 
     * @param string $format returned data format: 'json'|'object'|'array'
     * @return string/array default=JSON
     * @throws Exception
     */
    public function getGraph($format = 'json')
    {
        try {
            
            if (strlen($this->text)<2) {
                throw new Exception('You need to set text before sending request!');
            }
            $post_ = array(
                'data'   => $this->text,
                'token'  => $this->token,
                'method' => 'getGraph'
            );

            $result = $this->apiQuery($post_);

            if ($format === 'object') {
                $result = json_decode($result);
            }
            elseif ($format === 'array') {
                $result = json_decode($result, true);
            }
            $this->result_ = $result;
        }
        catch (Exception $ex) {
            throw $ex;
        }

        return $result;
    }

    /**
     * Sends text to LanguageDecoder using getFullTable method,
     * default returned data format is JSON,
     * set $format = 'object' to get objects array,
     * set $format = 'array' to get  associative array,
     * 
     * @param string $format returned data format: 'json'|'object'|'array'
     * @return string/array default=JSON
     * @throws Exception
     */
    public function getFullTable($format = 'json')
    {
        try{
            
            if (strlen($this->text)<2) {
                throw new Exception('You need to set text before sending request!');
            }
            $post_ = array(
                'data'   => $this->text,
                'token'  => $this->token,
                'method' => 'getFullTable'
            );
        
            $result = $this->apiQuery($post_);

            if ($format === 'object') {
                $result = json_decode($result);
            }
            elseif ($format === 'array') {
                $result = json_decode($result, true);
            }
            $this->result_ = $result;
        }
        catch (Exception $ex) {
            throw $ex;
        }

        return $result;
    }

    /**
     * Sends text to LanguageDecoder using getFullObject method,
     * default returned data format is JSON,
     * set $format = 'object' to get objects array,
     * set $format = 'array' to get  associative array,
     * 
     * @param string $format returned data format: 'json'|'object'|'array'
     * @return string/array default=JSON
     * @throws Exception
     */
    public function getFullObject($format = 'json')
    {
        try {
            
            if (strlen($this->text)<2) {
                throw new Exception('You need to set text before sending request!');
            }
            $post_ = array(
                'data'   => $this->text,
                'token'  => $this->token,
                'method' => 'getFullObject'
            );
        
            $result = $this->apiQuery($post_);

            if ($format === 'object') {
                $result = json_decode($result);
            }
            elseif ($format === 'array') {
                $result = json_decode($result, true);
            }
            $this->result_ = $result;
        }
        catch (Exception $ex) {
            throw $ex;
        }

        return $result;
    }

    /**
     * Validate and setup user's callback url.
     * 
     * @param string $callbackUrl valid callback url
     * @return boolean
     */
    public function setCallback($callbackUrl)
    {
        if (   empty($this->login)
            || empty($this->password)
            || empty($callbackUrl)
        ) {
            throw new Exception('Callback URL not set. To set Callback URL use'
                .'setCallbackUrl method.', 601);
        }

        $post_ = array(
            'login' => $this->login,
            'passwd' => $this->password,
            'method' => 'setCallbackUrl',
            'callback_url' => $callbackUrl
        );

        try {
            $this->apiQuery($post_, true);
        }
        catch (Exception $ex) {
            throw $ex;
        }

        return true;
    }

    /**
     * apiQuery sends $post_ array to apiUrl using curl, returns api response (JSON) on success
     * 
     * @param array $post_ $_POST variables
     * @param boolean $secure uses HTTPS if true, HTTP otherwise
     * @return string API response (JSON)
     * @throws \LD\API\Exception
     * @throws Exception
     */
    private function apiQuery($post_, $secure=false)
    {
        try {
            
            if (empty($post_)) {throw new Exception('_POST array is empty!');}
            
            $ch = curl_init();
            if ($this->useCallback) {
                $post_['callback'] = 1;
            }

            if ($secure) {
                curl_setopt($ch, CURLOPT_URL, 'https://' . $this->apiUrl);
            }
            else {
                curl_setopt($ch, CURLOPT_URL, 'http://' . $this->apiUrl);
            }
            curl_setopt($ch, CURLOPT_POST, count($post_));
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
            if ($secure) {
                curl_setopt($ch, CURLOPT_SSLVERSION, 3);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            }
            $json = curl_exec($ch);
            $error = curl_error($ch);
            $errno = curl_errno($ch);
            $info = curl_getinfo($ch);
            curl_close($ch);

            if ($errno!=0) {
                throw new Exception('Curl error: '.$error);
            }
            
            if (!isset($info['http_code']) || ($info['http_code']!=200)) {
                throw new Exception('Curl error: returned http_code='.$info['http_code'].' when trying to reach '.$this->apiUrl);
            }
            
            if (   ($decoded = json_decode($json))
                && !empty($decoded->Error)
            ) {
                throw new Exception($decoded->Error->desc, $decoded->Error->code);
            }
        }
        catch (Exception $ex) {
            throw $ex;
        }
        return $json;
    }
}
?>

