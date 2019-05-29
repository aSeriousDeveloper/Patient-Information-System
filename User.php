<?php

/**
 * @author Robert Lyons
 * This is the User Class
 * This controls the primary User functions, such as logging off and validating/updating tokens
 * Database functions are still controlled by DBHelper
 */

class User {
    
    var $timeout;
    
    function __construct($timeout) {
        $this -> timeout = $timeout;
    }
    /**
     * checks whether a user's logon has the correct credentials
     * @param DBHelper $dbHelper - database connection
     * @param String $username - user's email
     * @param String $password - User's password
     * @return string return whether the logon was a success or fail
     */
    public function checkLogon ($dbHelper, $username, $password) {
        //set result to whether the user exists in the first place
        $result = $dbHelper -> getUserPass($username);
        $resultArray = mysqli_fetch_array($result);
        //if user doesn't exist, set data to "FALSE"
        if(!$resultArray) {
            $dbPass = "FALSE";
            $userID = "FALSE";
        }
        //Otherwise get the username and password
        else {
            $dbPass = array_pop($resultArray);
            array_pop($resultArray);
        
            $userID = array_pop($resultArray);
        }
        //verify input password with user's password
        //if they match, generate the auth token and return it
        //also add auth record into database with the token
        if (password_verify($password, $dbPass)) {
            $rand = rand();
            $auth = md5($rand);
            
            $timeout = $this -> timeout;
            $dbHelper -> insertToken($auth, $userID, $timeout);
                  
                
            $json = json_encode($auth, JSON_FORCE_OBJECT);
            return $json;
        }
        //if password not verified, return 1
        //this can be any length except 32
        else {
            return "1";
        }
    }

    /**
     * validates the user's authorisation token
     * if it doesn't match an auth record in the database and the correct clearance, deny
     * @param DBHelper $dbHelper - DB Connection
     * @param String $auth - input auth token
     * @param int $clearance - required clearance level
     * @return String - whether or not the token passed clearance
     */
    public function validateToken($dbHelper, $auth, $clearance) {
    
        $result = $dbHelper -> checkToken($auth, $clearance);

        if ($result == "") {
            $timeout = $this -> timeout;
            $dbHelper -> updateToken($auth, $timeout);
        }

        return $result;
    }
    
    /**
     * checks if all data is validated, expects all shown inputs to exist
     * @param string[] $input Keyed list of user data
     * @param int $checkExists Run check for values existing (0 - do not check, 1 - check basics, 2 - check for patient ID)
     * @return string "OK" if all is valid, Error string of missing/invalid data if invalid
     */
    public static function validateUserInput($input, $checkExists) {
        if ($checkExists >= 1) {
            $exists = self::checkExists($input, $checkExists);
            if ($exists != "OK") {
                return $exists;
            }
        }
        
        $validData = self::validateUserData($input, $checkExists);
        if ($validData != "OK") {
            return $validData;
        }
        
        $validLogon = self::validateUserLogon($input);
        if ($validLogon != "OK") {
            return $validLogon;
        }
        
        $validAddress = self::validateUserAddress($input);
        if ($validAddress != "OK") {
            return $validAddress;
        }
        
        return "OK";
    }
    
    /**
     * Checks if the all of the User fields exists, only called if checkExists >= 1
     * @param string[] $input array of inputs for user
     * @param int $checkExists 1 - check only user values, 2 - check for Patient ID
     * @return string "OK" if all keys are present, returns missing keys otherwise
     */
    private static function checkExists($input, $checkExists) {
        $master = array(
            "user_name_first" => 0,
            "user_name_last" => 0, 
            "user_email" => 0, 
            "user_password" => 0, 
            "user_address_one" => 0, 
            "user_address_two" => 0, 
            "user_address_town" => 0, 
            "user_address_county" => 0, 
            "user_address_postcode" => 0, 
            "user_dob" => 0, 
            "user_img" => 0
            );
        if ($checkExists == 2) {
            $master["patient_id"] = 0;
        }
        $intersect = array_intersect_key($master, $input);
        if($intersect != $master) {
            return "missing keys " . json_encode(array_diff_key($master, $input));
        }
        return "OK";
    }
    
    /**
     * Function to validate data about the user, does not include email/pass, or address
     * @param string[] $input - array of inputs for user
     * @param int $checkExists 1 - check only user values, 2 - check for Patient ID
     * @return string "OK" if all inputs valid, otherwise return first invalid string
     */
    private static function validateUserData($input, $checkExists) {
        if(array_key_exists("patient_id", $input) && $checkExists == 2 && !preg_match("/^\d{3}-\d{3}-\d{4}$/", $input["patient_id"])) {
            return "Invalid NHS Number, send as string in XXX-XXX-XXXX format";
        }
        
        if(array_key_exists("user_name_first", $input) && (strlen($input["user_name_first"]) < 1 || strlen($input["user_name_first"]) > 64)) {
            return "missing firstname or firstname too long";
        }
        
        if(array_key_exists("user_name_last", $input) && (strlen($input["user_name_last"]) < 1 || strlen($input["user_name_last"]) > 64)) {
            return "missing surname or surname too long";
        }
        
        if (array_key_exists("user_dob", $input) && !preg_match("/^(19|20)\d\d-(0[1-9]|1[0-2])-(0[1-9]|[12][0-9]|3[01])$/", $input["user_dob"])) {
            return "missing or invalid date of birth, format as YYYY-MM-DD";
        }
        
        if (array_key_exists("user_img", $input) && strlen($input["user_img"]) < 1){
            return "no image attached";
        }
        return "OK";
    }
    
    /**
     * validate user logon info
     * @param Array $input - array of all of the JSON inputs
     * @return string - OK if correct, otherwise the validation issue
     */
    private static function validateUserLogon($input) {
        if(array_key_exists("user_email", $input) && !filter_var($input["user_email"], FILTER_VALIDATE_EMAIL)) {
            return "Invalid Email";
        }
        
        if (array_key_exists("user_password", $input) && (strlen($input["user_password"]) < 8 || strlen($input["user_password"]) > 64)) {
            return "password missing, or is below 8 characters, or above 64";
        }
        
        return "OK";
    } 
    
    private static function validateUserAddress($input) {
        if(array_key_exists("user_address_one", $input) && (strlen($input["user_address_one"]) < 1 || strlen($input["user_address_one"]) > 64)) {
            return "address 1 missing or too long";
        }
        
        if(array_key_exists("user_address_two", $input) && strlen($input["user_address_two"]) > 64) {
            return "address 2 null or too long";
        }
        
        if(array_key_exists("user_address_town", $input) && (strlen($input["user_address_town"]) < 1 || strlen($input["user_address_town"]) > 64)) {
            return "address town missing or too long";
        }
        
        if(array_key_exists("user_address_county", $input) && (strlen($input["user_address_county"]) < 1 || strlen($input["user_address_county"]) > 32)) {
            return "address county missing or too long";
        }
        
        if(array_key_exists("user_address_postcode", $input) && !preg_match('/^([a-zA-Z]{1,2}\d{1,2})\s*?(\d[a-zA-Z]{2})$/', $input["user_address_postcode"])) {
            return "Invalid Postcode";
        }
        
        return "OK";
    }
}
