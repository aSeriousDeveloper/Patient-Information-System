<?php

/*
 * Copyright (C) 2019 Robert Lyons
 */

/**
 * Object that deals with Allergy information from JSON input
 *
 * @author Robert Lyons
 */
class Allergy {
    var $allergy_patient_id;
    var $allergy_agent;
    var $allergy_reaction;
    var $allergy_severity;
    var $allergy_source;
    var $allergy_status; 
    
    //Constructor
    function __construct ($allergyArray) {
        $exists = $this -> checkExists($allergyArray);
        if($exists != "OK") {
            die($exists);
        }
        $this -> allergy_patient_id = $allergyArray["patient_allergy_patient_id"];
        $this -> allergy_agent = $allergyArray["patient_allergy_agent_id"];
        $this -> allergy_reaction = $allergyArray["patient_allergy_reaction_id"];
        $this -> allergy_severity = $allergyArray["patient_allergy_severity"];
        $this -> allergy_source = $allergyArray["patient_allergy_source"];
        $this -> allergy_status = $allergyArray["patient_allergy_status"];
    }
    
    /**
     * Validate information required to add a new allergy
     * @return string OK if info is valid, error message otherwise
     */
    public function validateAllergyInfo() {
        if(!preg_match("/^\d{3}-\d{3}-\d{4}$/", $this -> allergy_patient_id)) {
            return "Invalid NHS Number, send as string in XXX-XXX-XXXX format";
        }
        
        if(!is_int($this -> allergy_agent) || !is_int($this -> allergy_reaction)) {
            return "Allergy Agent and Reaction must be passed using their ID's as Integers, not Descriptions or Strings";
        }
        
        if (!in_array($this -> allergy_severity, array("Mild", "Moderate", "Severe"), false)) {
            return "Invalid Severity Value, must be Mild, Moderate, or Severe";
        }
        
        if (!in_array($this -> allergy_source, array("Reported by Practice", "Reported by Patient", "Allergy History", "Transition of Care", "Referral"), false)) {
            return "Invalid Source Value, must be Reported by Practice, Reported by Patient, Allergy History, Transition of Care, or Referral";
        }
        
        if (!in_array($this -> allergy_status, array("Active", "Inactive", "Resolved"), false)) {
            return "Invalid Status Value, must be Active, Inactive, or Resolved";
        }
        
        return "OK";
    }
    
    /**
     * Check if all of the allergy information exists within the array
     * @param Array $allergyArray - array of allergy info
     * @return string OK if all rows are there, return missing keys otherwise
     */
    private function checkExists($allergyArray) {
        $master = array (
            "patient_allergy_patient_id" => 0,
            "patient_allergy_agent_id" => 0,
            "patient_allergy_reaction_id" => 0,
            "patient_allergy_severity" => 0,
            "patient_allergy_source" => 0,
            "patient_allergy_status" => 0,
        );
        $intersect = array_intersect_key($master, $allergyArray);
        if($intersect != $master) {
            return "missing keys " . json_encode(array_diff_key($master, $allergyArray));
        }
        else {
            return "OK";
        }
    }
}
