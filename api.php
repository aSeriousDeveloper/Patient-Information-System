<?php
header("Access-Control-Allow-Origin: *");
/**
 * @author Robert Lyons
 * This is an API for a medical system back-end
 * Its current functions include viewing patients and their medications/allergies
 * As well as the doctors who treat them
 * Doctors may log in to view this information
 */

//import other classes
require_once("User.php");
require_once("DBHelper.php");
require_once("Allergy.php");
require_once("Medication.php");

//instantiate objects
//DB is located at localhost
//Timeout for logon currently set to 120 minutes
$dbHelper = new DBHelper("localhost","root","","medapp");
//TODO separate Auth/User Commands, create AuthHelper?
$user = new User("120 MINUTE");

//re-enable once decided on using separate requests for different functions
//method = filter_input(INPUT_SERVER, 'REQUEST_METHOD');
//get request and JSON input
$request = explode('/', trim(filter_input(INPUT_SERVER, 'PATH_INFO'),'/'));
$input = json_decode(file_get_contents('php://input'),true);

//get first argument from url request and search for option via switch
$command = array_shift($request);
switch ($command) {

    //Patient Case
    //If search is not active, return list of all active patients for doctor logged on
    //If search is active, return patients matching search
    case "patient": {
        
        $auth = $input["token"];
        $valid = $user -> validateToken($dbHelper, $auth, 2);
        if ($valid  != "") {
            echo $valid;
            break;
        }
        
        //SwitchCase for patient options
        switch (array_shift($request)) {
            
            case "doctor": {
                $patientID = $input["patient"];
                $result = $dbHelper ->findDoctor($patientID);
                $json = json_encode(mysqli_fetch_all($result), JSON_FORCE_OBJECT);
                echo $json;
                break;
            }
            
            //List all patients associated with doctor logged in
            case "list": {
                $result = $dbHelper -> listPatients($auth);
                $json =  json_encode(mysqli_fetch_all($result), JSON_FORCE_OBJECT);
                echo $json;
                break;
            }
            
            //Create a new patient
            case "new": {
                $valid = User::validateUserInput($input, 2);
                if ($valid != "OK") {
                    echo $valid;
                    break;
                }
                $password = $input["user_password"];
                $input["user_password"] = password_hash($password, PASSWORD_BCRYPT);
                
                $imgExplode = explode(",", $input["user_img"]);
                $img = $imgExplode[1];
                $filepath = "img/" . $input["user_name_first"] . "_" . time() . ".jpg";
                file_put_contents($filepath, base64_decode($img));
                $input["user_img"] = $filepath;
                
                $result = $dbHelper -> createPatient($input, 1);
                echo $result;
                break;
            }
            
            //modify an existing patient's details
            case "modify": {
                $valid = User::validateUserInput($input, 0);
                if ($valid != "OK") {
                    echo $valid;
                    break;
                }
                //if an image exists, turn from base 64 into an image file
                //save image then input filepath into array for DB
                if(array_key_exists("user_img", $input)) {
                    $imgExplode = explode(",", $input["user_img"]);
                    $img = $imgExplode[1];
                    $filepath = "img/" . $input["user_name_first"] . "_" . time() . ".jpg";
                    file_put_contents($filepath, base64_decode($img));
                    $input["user_img"] = $filepath;
                }
                //add userID to unique variable
                //remove token and user ID from array
                //then modify user and return result
                $userID = $input["user_id"];
                unset($input["user_id"]);
                unset($input["token"]);
                $result = $dbHelper -> modifyUser($input, $userID);
                echo $result;
                break;
            }
            
            //Search for patients
            case "search": {
                $result = $dbHelper -> searchPatients($input);
                $json = json_encode(mysqli_fetch_all($result));
                echo $json;
                break;
            }
            
            //Select a patient, along with their doctor data
            case "select": {
                $patientID = $input["patient_id"];
                $result = $dbHelper -> selectPatient($patientID);
                $json = json_encode(mysqli_fetch_assoc($result), JSON_FORCE_OBJECT);
                echo $json;
                break;
            }
            
            //if patient request not recognised...
            default: {
                echo "Invalid Patient Request.";
                break;
            }
        }
        
        break;
    }
    
    //Allergy Case
    //Controls pretty much everything to do with allergies
    //Pull up list of a patients allergies, get the agents and reactions lists
    //add/modify allergies to patients
    case "allergy": {
        $auth = $input["token"];
        $valid = $user -> validateToken($dbHelper, $auth, 2);
        if ($valid  != "") {
            echo $valid;
            break;
        }
        
        switch (array_shift($request))  {
            //list a patient's allergies
            case "list": {
                $patientID = $input["patient_id"];
                $result = $dbHelper -> listAllergies($patientID);
                $json = json_encode(mysqli_fetch_all($result));
                echo $json;
                break;
            }
            //list all agents within the database
            case "agents": {
                $result = $dbHelper ->listAgents();
                $json = json_encode(mysqli_fetch_all($result));
                echo $json;
                break;
            }
            //list all reactions within the database
            case "reactions": {
                $result = $dbHelper ->listReactions();
                $json = json_encode(mysqli_fetch_all($result));
                echo $json;
                break;
            }
            //add a new allergy for a patient
            case "new": {
                $allergy = new Allergy($input);
                $valid = $allergy ->validateAllergyInfo();
                if($valid != "OK") {
                    echo $valid;
                    break;
                }
                $result = $dbHelper -> createAllergy($allergy);
                echo $result;
                break;
            }
        }
        
        break;
    }
    
    //Medication Case
    //Controls pretty much everything to do with medications
    //Pull up list of a patients medications, add/modify allergies to patients
    case "medication": {
        $auth = $input["token"];
        $valid = $user -> validateToken($dbHelper, $auth, 2);
        if ($valid  != "") {
            echo $valid;
            break;
        }
        //lists all of the medications in the database
        switch (array_shift($request))  {
            case "medications": {
                $result = $dbHelper -> listMedTypes();
                $json = json_encode(mysqli_fetch_all($result));
                echo $json;
            }
            //lists all of a patient's medications
            case "list": {
                $patientID = $input["patient_id"];
                $result = $dbHelper -> listMedications($patientID);
                $json =  json_encode(mysqli_fetch_all($result));
                echo $json;
                break;
            }
            //adds a new medication to a patient
            case "new": {
                $medication = new Medication($input);
                $valid = $medication ->validateMedicationInfo();
                if($valid != "OK") {
                    echo $valid;
                    break;
                }
                $result = $dbHelper ->createMedication($medication);
                echo $result;
                break;
            }
        }
        
        break;
    }
    
    //User Case
    //By default, shows current user for token
    //Admins can view a specific ser if they provide a UserID
    case "user": {
        
        $auth = $input["token"];
        
        $valid = $user -> validateToken($dbHelper, $auth, 1);
        if ($valid  != "") {
            echo $valid;
            break;
        }
        
        //Admins only, get user data from anyone in the DB from their User ID
        if (array_key_exists("user_id", $input)) {
            $valid = $user -> validateToken($dbHelper, $auth, 3);
            if ($valid  != "") {
                echo $valid;
                break;
            }
            $result = $dbHelper -> findUser($input["user_id"]);
            
        }
        //Standard case, shows user currently logged in
        else {
            $result = $dbHelper -> getUser($auth);
        }
        
        $json = json_encode(mysqli_fetch_all($result), JSON_FORCE_OBJECT);
        echo $json;
        break;
    }
    
    //Logon case
    //Self explanatory, controls logging on
    case "logon": {
        $userEmail = $input["user_email"];
        $password = $input["user_password"];
        
        $token = $user -> checkLogon($dbHelper, $userEmail, $password);
        if (strlen($token) == 34) {
            echo $token;
        }
        else {
            echo "Invalid Logon Credentials.";
        }
        
        break;
    }
    
    //Logoff case
    //uses token given to close authorisation
    case "logoff": {
        $auth = $input["token"];
        $logoff = $dbHelper -> logoff($auth);
        echo $logoff;
        break;
    }
    
    //Quick way to get a hash value, delete later
    case "hashme": {
        $hashPre = array_shift($request);
        $hash = password_hash($hashPre, PASSWORD_BCRYPT);
        echo $hash;
        break;
    }
        
    //If command not recognised...
    default: {
        echo "command not recognised";
        break;
    }
    //close db connection and exit
    $dbHelper -> closeConnection();
    return;
}