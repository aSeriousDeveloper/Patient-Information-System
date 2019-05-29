<?php

/*
 * Copyright (C) 2019 Robert Lyons
 */

/**
 * Object that deals with the Medication info from JSON input
 *
 * @author Robert Lyons
 */
class Medication {
    var $medication_patient_id;
    var $medication_type;
    var $medication_dosage;
    var $medication_date_start;
    var $medication_date_end;
    var $medication_usage; 
    
    //Constructor
    function __construct ($medicationArray) {
        $exists = $this -> checkExists($medicationArray);
        if($exists != "OK") {
            die($exists);
        }
        $this -> medication_patient_id = $medicationArray["patient_medication_patient_id"];
        $this -> medication_type = $medicationArray["patient_medication_medication_id"];
        $this -> medication_dosage = $medicationArray["patient_medication_dosage"];
        $this -> medication_date_start = $medicationArray["patient_medication_date_start"];
        $this -> medication_date_end = $medicationArray["patient_medication_date_end"];
        $this -> medication_usage = $medicationArray["patient_medication_usage"];
    }
    
    /**
     * Validate medication input
     * @return string OK if valid, error with invalid info otherwise
     */
    public function validateMedicationInfo() {
        if(!preg_match("/^\d{3}-\d{3}-\d{4}$/", $this -> medication_patient_id)) {
            return "Invalid NHS Number, send as string in XXX-XXX-XXXX format";
        }
        
        if(!is_int($this -> medication_type)) {
            return "Medication Type must be passed using its ID as Integer, not as a Description or String";
        }
        
        if (strlen($this -> medication_dosage) < 1 || strlen($this -> medication_dosage) > 16) {
            return "Missing medication dosage, or is longer than 16 characters";
        }
        
        if (!preg_match("/^(19|20)\d\d-(0[1-9]|1[0-2])-(0[1-9]|[12][0-9]|3[01])$/", $this -> medication_date_start)) {
            return "Invalid start date, must follow YYYY-MM-DD format";
        }
        
        if (!preg_match("/^(19|20)\d\d-(0[1-9]|1[0-2])-(0[1-9]|[12][0-9]|3[01])$/", $this -> medication_date_end)) {
            return "Invalid end date, must follow YYYY-MM-DD format";
        }
        
        if (strlen($this -> medication_usage) < 1 || strlen($this -> medication_usage) > 128) {
            return "Missing medication dosage, or is longer than 128 characters";
        }
        
        return "OK";
    }
    
    /**
     * Check if all of the medication information exists within the array
     * @param Array $medicationArray - array of medication info
     * @return string OK if all rows are there, return missing keys otherwise
     */
    private function checkExists($medicationArray) {
        $master = array (
            "patient_medication_patient_id" => 0,
            "patient_medication_medication_id" => 0,
            "patient_medication_dosage" => 0,
            "patient_medication_date_start" => 0,
            "patient_medication_date_end" => 0,
            "patient_medication_usage" => 0,
        );
        $intersect = array_intersect_key($master, $medicationArray);
        if($intersect != $master) {
            return "missing keys " . json_encode(array_diff_key($master, $medicationArray));
        }
        else {
            return "OK";
        }
    }
}
