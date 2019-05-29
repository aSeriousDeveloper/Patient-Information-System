<?php

/**
 * @author Robert Lyons
 * This class controls all of the data that is CRUDded from the primary database
 * This includes search functions, updating patient data and reading medical data
 * TODO Completely refactor SQL statements in here, this class should be just for SQL connections, data itself can be handled within other classes
 */

class DbHelper {
    //Constructor
    var $link;
    function __construct($host, $user, $pass, $db) {
        $this -> link = mysqli_connect($host, $user, $pass, $db);
        if ($this -> link -> connect_error) {
            die('Failed to connect to MySQL - ' . $this -> link -> connect_error);
        }
        $this -> link -> set_charset("utf8");
    }
    
    /**
     * Close Database Connection
     */
    public function closeConnection() {
        mysqli_close($this -> link);
    }
    
//-----AUTHORISATION DATABASE FUNCTIONS-----
//THIS SECTION RELATES TO USER AUTHORISATION, INCLUDING LOGGING ON AND OFF
    /**
     * Check if token matches the one in the database
     * @param String $auth - Authorisation input token
     * @param int $clearance - Required clearance level
     * @return string empty if valid, error if invalid
     */
    public function checkToken ($auth, $clearance) {
        $query = "select `user_type` "
            . "from `user` "
            . "inner join `authorisation` on `USER_ID` = `authorisation_user_id` and `AUTHORISATION_ID` = (?) and AUTHORISATION_TIMEOUT > CURRENT_TIMESTAMP  "
            . "where `user_type` >= (?)";
        
        $stmt = $this -> link -> prepare($query);
        $stmt ->bind_param("si", $auth, $clearance);
        $stmt -> execute();
        
        $result = $stmt -> get_result();
        if (mysqli_num_rows($result) == 0) {
            $stmt = $this -> link ->prepare("delete from `AUTHORISATION` where AUTHORISATION_TIMEOUT < CURRENT_TIMESTAMP");
            $stmt -> execute();
            return "invalid token";
        }
        
        return "";
    }
    
    /**
     * Get a user's password from their username
     * @param String $username - input username
     * @return Result SQL result from database
     */
    public function getUserPass($username) {
        //SQL statement to get User ID and password for checking logon and generating token
        $query = "select USER_ID, USER_PASSWORD from USER where USER_EMAIL = (?)";
        
        //prepare, execute and return statement results
        $stmt = $this -> link -> prepare($query);
        $stmt -> bind_param("s", $username);
        $stmt -> execute();
        return $stmt -> get_result();
    }
    
    public function insertToken($auth, $userID, $timeout) {
        //SQL statement to insert token into auth table with appropriate ID and timeout
        //update with new token if record already exists
        //using timeout variable within query itself is fine here, only declared internally and not set by user
        $query = "insert into AUTHORISATION "
                    . "(`AUTHORISATION_ID`, `AUTHORISATION_USER_ID`, `AUTHORISATION_TIMEOUT`) values "
                    . "((?), (?), ADDDATE(CURRENT_TIMESTAMP, INTERVAL $timeout)) "
                    . "on duplicate key update `AUTHORISATION_ID` = (?), `AUTHORISATION_TIMEOUT` = ADDDATE(CURRENT_TIMESTAMP, INTERVAL $timeout)";
        
        //prepare, execute and return statement results
        $stmt = $this -> link -> prepare($query);
        $stmt -> bind_param("sis", $auth, $userID, $auth);
        $stmt -> execute();
    }
    
    /**
     * Logoff from the system and remove auth record from DB
     * @param String $auth input auth token to be removed
     * @return int number of affected rows, should = 1 if successful
     */
    public function logoff($auth) {
        //SQL query to remove authorisation record based on the auth ID provided
        $query = "delete from AUTHORISATION where AUTHORISATION_ID = (?)";
        
        //prepare & execute
        $stmt = $this -> link -> prepare($query);
        $stmt -> bind_param("s", $auth);
        $stmt -> execute();
        $logoff = $stmt -> affected_rows;
        return $logoff;
    }
    
    /**
     * Updates timeout on auth token
     * @param String $auth the auth token to update
     * @param String/Time $timeout time to update auth timeout to
     */
    public function updateToken ($auth, $timeout) {
        $query = "update `AUTHORISATION` set `AUTHORISATION_TIMEOUT` = ADDDATE(CURRENT_TIMESTAMP, INTERVAL $timeout) where `AUTHORISATION_ID` = (?)";
        
        $stmt = $this -> link ->prepare($query);
        $stmt ->bind_param("s", $auth);
        $stmt ->execute();
    }
    
//-----USER DATABASE FUNCTIONS-----
//THIS SECTION COLLECTS AND PROCESSES USER DATA
    
    /**
     * Create a new user within the database
     * @param Array $input - input array of user details
     * @param int $userType - User credentials level
     * @return String User ID of created User, or error if not successful
     */
    public function createUser($input, $userType) {
        $queryUser = "insert into `USER` (`USER_NAME_FIRST`, `USER_NAME_LAST`, "
                . "`USER_EMAIL`, `USER_ADDRESS_ONE`, `USER_ADDRESS_TWO`, "
                . "`USER_ADDRESS_TOWN`, `USER_ADDRESS_COUNTY`, "
                . "`USER_ADDRESS_POSTCODE`, `USER_DOB`, "
                . "`USER_PASSWORD`, `USER_TYPE`, `USER_IMG`)"
                . "values ((?), (?), "
                . "(?), (?), (?), "
                . "(?), (?), "
                . "(?), (?), "
                . "(?), $userType, (?))";
        
        //prepare & execute
        $stmtUser = $this -> link -> prepare($queryUser);
        $stmtUser -> bind_param("sssssssssss", 
                $input["user_name_first"], $input["user_name_last"],
                $input["user_email"], $input["user_address_one"], $input["user_address_two"], 
                $input["user_address_town"], $input["user_address_county"],
                $input["user_address_postcode"], $input["user_dob"],
                $input["user_password"], $input["user_img"]
                );
        $stmtUser -> execute();
        if (strlen($stmtUser -> error) != 0) {
            return $stmtUser -> error;
        }
        return $stmtUser -> insert_id;
    }
    
    /**
     * modifies an existing user in the database, using their User ID
     * @param string[] $input array of users inputs for modifying a specific user
     * @return int returns affected rows (should equal 1)
     */
    public function modifyUser($input, $userID) {
        $queryUser = "update USER set ";
        
        foreach (array_keys($input) as $column) {
            $queryUser = $queryUser . 
                    $this -> link -> real_escape_string($column) . 
                    " = (?), ";
        }
        
        $input["user_id"] = $userID;
        $queryUser = substr($queryUser, 0, -2) . " where USER_ID = (?)";
        
        //prepare & execute
        $stmtUser = $this -> link -> prepare($queryUser);
        $paramString = str_repeat("s", count($input));
        $bindParams["define"] = &$paramString;
        foreach($input as $key => $value) {
            $bindParams[$key] = &$value;
            unset ($value);
        }
        
        call_user_func_array(array($stmtUser, "bind_param"), $bindParams);
        $stmtUser -> execute();
        return $stmtUser -> affected_rows;
    }
    
    /**
     * Find a user given a specific user ID
     * @param int $userID The USER ID you're trying to find
     * @return Result DB Query Result
     */
    public function findUser($userID) {
        //SQL query to get user information from given userID
        $query = "select USER_NAME_FIRST, USER_NAME_LAST, USER_EMAIL from USER where USER_ID = (?)";
        
        //prepare, execute and return statement results
        $stmt = $this -> link -> prepare($query);
        $stmt -> bind_param("i", $userID);
        $stmt -> execute();
        return $stmt -> get_result();
    }
    
    /**
     * Get user info for a given auth token
     * Different from findUser as this only gets the user info for the user logged in, not any user on request
     * @param String $auth - input auth token
     * @return Result, SQL Query Result
     */
    public function getUser($auth) {
        //SQL query to select User details associated with given auth ID
        $query = "select USER_ID, USER_NAME_FIRST, USER_NAME_LAST, USER_EMAIL from USER inner join `AUTHORISATION` on USER_ID = AUTHORISATION_USER_ID and AUTHORISATION_ID = (?)";
        
        //prepare, execute and return statement results
        $stmt = $this -> link -> prepare($query);
        $stmt -> bind_param("s", $auth);
        $stmt -> execute();
        return $stmt -> get_result();
    }
  
//-----PATIENT DATABASE FUNCTIONS-----
//THIS SECTION COLLECTS AND PROCESSES PATIENT DATA
    /**
     * creates a new patient within the database
     * @param Array $input array of patient information
     * @return type
     */
    public function createPatient($input) {
        //run create user and get the User ID
        $userID = $this -> createUser($input, 1);
        if (!is_int($userID)) {
            return $userID;
        }
        
        //Get the Doctor ID of the signed in doctor to assign them to the patient
        $doctor = $this -> getUser($input["token"]);
        $doctorRow = mysqli_fetch_assoc($doctor);
        $doctorID = $doctorRow["USER_ID"];
        
        //build query
        $queryPatient = "insert into `PATIENT` (`PATIENT_ID`, `PATIENT_USER_ID`, `PATIENT_DOCTOR_ID`) "
                . "values ((?), (?), (?))";
        
        //bind params
        $stmtPatient = $this -> link -> prepare($queryPatient);
        $stmtPatient -> bind_param("sii", $input["patient_id"], $userID, $doctorID);
        $stmtPatient -> execute();
        $newPatient = $stmtPatient -> affected_rows;
        
        //if there's an error, return it
        //otherwise return the new User ID and number of affected rows in patient able
        if (strlen($stmtPatient -> error) != 0) {
            return $stmtPatient -> error;
        }
        return $userID . " " . $newPatient;
    }
    
    /**
     * List a doctor's assigned patients
     * @param string $auth - input auth token
     * @return Result - SQL Query Result
     */
    public function listPatients($auth) {
        //SQL query to prepare, Authorisation ID is required for statement
        $query = "select patient_id, user_name_first, user_name_last, user_email, USER_IMG, USER_ID "
                . "from authorisation "
                . "inner join patient on patient_doctor_id = authorisation_user_id "
                . "inner join user on patient_user_id = user_id "
                . "where authorisation_id = (?)";
        
        //prepare, execute and return statement results
        $stmt = $this -> link -> prepare($query);
        $stmt -> bind_param("s", $auth);
        $stmt -> execute();
        return $stmt -> get_result();
    }
    
    /**
     * Searches patients in database
     * Can be adapted to search all users, but only relevant for patients for now
     * @param type $searchQueries - variable size input JSON of search criteria
     * @return Result - SQL Query Result
     */
    public function searchPatients($searchQueries) {
        $query = "select patient_id, user_name_first, user_name_last, user_email, "
                . "user_address_one, user_address_two, user_address_town, "
                . "user_address_county, user_address_postcode, user_dob, user_id "
                . "from user inner join patient on patient_user_id = user_id "
                . "where user_type = 1 ";
        
        unset ($searchQueries["token"]);
        foreach ($searchQueries as $columnName => $column) {
            $searchQueries[$columnName] = "%$column%";
            $query = $query . "and " . $this -> link -> real_escape_string($columnName) . " like (?) ";
        }
        
        //prepare & execute
        $stmt = $this -> link -> prepare($query);
        $paramString = str_repeat("s", count($searchQueries));
        $bindParams["define"] = &$paramString;
        foreach($searchQueries as $columnName => $column) {
            $bindParams[$columnName] = &$searchQueries[$columnName];
        }
        
        call_user_func_array(array($stmt, "bind_param"), $bindParams);    
        $stmt -> execute();
        return $stmt -> get_result();
    }
    
    /**
     * Select a specific patient from the database via their Patient/NHS ID
     * @param string $patientID - input patient ID
     * @return Result - SQL Query Result
     */
    public function selectPatient($patientID) {
        //SQL query to select patient and their doctor info, based on the provided Patient ID
        $query = "select PATIENT_ID, USER_NAME_FIRST, USER_NAME_LAST, USER_EMAIL, "
                . "USER_ADDRESS_ONE, USER_ADDRESS_TWO, "
                . "USER_ADDRESS_TOWN, USER_ADDRESS_COUNTY, "
                . "USER_ADDRESS_POSTCODE, USER_DOB, USER_IMG, USER_ID "
                . "from patient "
                . "inner join user on patient_user_id = user_id "
                . "where patient_id = (?)";
        
        //prepare, execute and return statement results
        $stmt = $this -> link -> prepare($query);
        $stmt -> bind_param("i", $patientID);
        $stmt -> execute();
        return $stmt -> get_result();
    }
    
//-----DOCTOR DATABASE FUNCTIONS-----
//THIS SECTION COLLECTS AND PROCESSES DOCTOR DATA
    /**
     * Get doctor information for a specific patient
     * @param string $patientID - input patient ID
     * @return Result - SQL Query Result
     */
    public function findDoctor($patientID) {
        $query = "select USER_ID, USER_NAME_FIRST, USER_NAME_LAST, USER_EMAIL "
                . "from PATIENT "
                . "inner join USER on PATIENT_DOCTOR_ID = USER_ID "
                . "where PATIENT_ID = (?)";
        
        $stmt = $this -> link ->prepare($query);
        $stmt ->bind_param("i", $patientID);
        $stmt -> execute();
        return $stmt -> get_result();
    }


//-----ALLERGY DATABASE FUNCTIONS-----
//THIS SECTION COLLECTS AND PROCESSES ALLERGY DATA
    /**
     * List a patient's allergies
     * @param String $patientID - input patient ID
     * @return Result - SQL Query Result
     */
    public function listAllergies($patientID) {
        //build query
        $query = "select patient_allergy_id, allergy_agent_description, allergy_reaction_description, patient_allergy_severity, patient_allergy_source, patient_allergy_status "
                . "from patient_allergy "
                . "inner join allergy_agent on patient_allergy_agent_id = allergy_agent_id "
                . "inner join allergy_reaction on patient_allergy_reaction_id = allergy_reaction_id "
                . "where patient_allergy_patient_id = (?)";
        
        //bind & execute
        $stmt = $this -> link -> prepare($query);
        $stmt -> bind_param("i", $patientID);
        $stmt -> execute();
        return $stmt -> get_result();
    }
    
    /**
     * List agents within the database
     * Due to large number of agents this might be best to cut down
     * @return Result - SQL Query Result
     */
    public function listAgents() {
        $query = "select ALLERGY_AGENT_ID, ALLERGY_AGENT_DESCRIPTION from ALLERGY_AGENT";
        
        $result = $this -> link -> query($query);
        return $result;
    }
    
    /**
     * List reactions within the database
     * @return Result - SQL Query Result
     */
    public function listReactions() {
        $query = "select ALLERGY_REACTION_ID, ALLERGY_REACTION_DESCRIPTION from ALLERGY_REACTION";
        
        $result = $this -> link -> query($query);
        return $result;
    }
    
    /**
     * Create a new allergy record for a patient
     * @param Array $allergy - JSON input array
     * @return int - number of affected rows, should = 1
     */
    public function createAllergy($allergy) {
        $query = "insert into PATIENT_ALLERGY "
                . "(PATIENT_ALLERGY_PATIENT_ID, PATIENT_ALLERGY_AGENT_ID, "
                . "PATIENT_ALLERGY_REACTION_ID, PATIENT_ALLERGY_SEVERITY, "
                . "PATIENT_ALLERGY_SOURCE, PATIENT_ALLERGY_STATUS) "
                . "values (?,?,?,?,?,?)";
        
        $stmt = $this -> link ->prepare($query);
        $stmt -> bind_param("siisss",
                $allergy -> allergy_patient_id,
                $allergy -> allergy_agent,
                $allergy -> allergy_reaction,
                $allergy -> allergy_severity,
                $allergy -> allergy_source,
                $allergy -> allergy_status);
        $stmt ->execute();
        return $stmt -> affected_rows;
    }
    
//-----MEDICATION DATABASE FUNCTIONS-----
//THIS SECTION COLLECTS AND PROCESSES MEDICATION DATA
    
    /**
     * List all of the medications within the DB
     * Due to large number of agents this might be best to cut down
     * @return Result - SQL Query Result
     */
    public function listMedTypes() {
        $query = "select MEDICATION_ID, MEDICATION_NAME from MEDICATION";
        
        $result = $this -> link -> query($query);
        return $result;
    }
    
    /**
     * List all of a patient's medications
     * @param String $patientID - input patient ID
     * @return Result - SQL Query Result
     */
    public function listMedications($patientID) {
        $query = "select patient_medication_id, medication_name, patient_medication_dosage, patient_medication_date_start, patient_medication_date_end, patient_medication_usage "
                . "from patient_medication "
                . "inner join medication on patient_medication_medication_id = medication_id "
                . "where patient_medication_patient_id = (?)";

        $stmt = $this -> link -> prepare($query);
        $stmt -> bind_param("i", $patientID);
        $stmt -> execute();
        return $stmt -> get_result();
    }
    
    /**
     * Create a new medication record for a patient
     * @param Array $medication - Input JSON Array of medication information
     * @return int - number of affected rows, should = 1 if no errors
     */
    public function createMedication($medication) {
        $query = "insert into PATIENT_MEDICATION "
                . "(PATIENT_MEDICATION_PATIENT_ID, PATIENT_MEDICATION_MEDICATION_ID, "
                . "PATIENT_MEDICATION_DOSAGE, PATIENT_MEDICATION_DATE_START, "
                . "PATIENT_MEDICATION_DATE_END, PATIENT_MEDICATION_USAGE) "
                . "values (?,?,?,?,?,?)";
        
        $stmt = $this -> link ->prepare($query);
        $stmt -> bind_param("sissss",
                $medication -> medication_patient_id,
                $medication -> medication_type,
                $medication -> medication_dosage,
                $medication -> medication_date_start,
                $medication -> medication_date_end,
                $medication -> medication_usage);
        $stmt ->execute();
        return $stmt -> affected_rows;
    }
}
