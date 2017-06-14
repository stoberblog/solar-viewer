<?php

// Database Config
$DATABASE_ADDR = "127.0.0.1";
$DATABASE_USER = "sUser";
$DATABASE_PASSWD = "sPassed";
$DATABASE_DB = "solarMB";

// Defaults
$DFT_PERIOD_INT = 4320; // Minutes (3 days)
$DFT_PERIOD_DAY = 50; // Days (50 days)


header("Content-Type: text/plain");
header("Access-Control-Allow-Origin: *");

// Export
if (isset($_GET['exp'])) {
        $export = $_GET['exp'];
        $export = addslashes(strip_tags($export));
//      echo "Export";
        // Set content-type to download
        if ($export == "t" || $export == "T" || $export == "true" || $export == "True") {
                header("Content-Disposition: attachment;filename=\"solarData-".date("Ymd-His").".txt\"");
        }
}

// Data request type
if (isset($_GET['t'])) {
        $type = $_GET['t'];
        $type = addslashes(strip_tags($type));

        if ($type == "status") {
                echo "Script version test 1.0\n";
                echo "This is a test script that allows downloading of data from the solar database\n";
        }
        else if ($type == "conf") { // Get last config for the UID given
                configUID();
        }
        else if ($type == "daily") {
                //echo "Daily\n";
                dailyType();
        }
        else if ($type == "int") {
                //echo "Interval\n";
                intervalType();
        }
        else {
                echo "Invalid parameters";
        }
}
else {
        echo "Invalid Type parameters";
}



// User ID (used for settings)
// NOT IMPLEMENTED YET!
if (isset($_GET['uid'])) {
        $type = $_GET['uid'];
}
// Update - only get newest data since last request
// Can only be used if userid given
// NOT IMPLEMENTED YET!
if (isset($_GET['upd'])) {
        $type = $_GET['upd'];
}
// Time Start (when not auto update)
// NOT IMPLEMENTED YET!
else if (isset($_GET['tstart'])) {
        $type = $_GET['tstart'];
}


// Time Period
// NOT IMPLEMENTED YET!
if (isset($_GET['tperiod'])) {
        $type = $_GET['tperiod'];
}


/*
 *
 * Function prints last config for the UID
 *
 */
function configUID() {
        // User ID (used for settings)
        if (isset($_GET['uid'])) {
                $type = $_GET['uid'];
                echo "Configuration for UID\n";
        }
        else {
                echo "NO UID Given\n";
        }
}



/*
 *
 * Function that deals with daily data
 *
 */
function dailyType() {
        global $DATABASE_ADDR, $DATABASE_DB, $DATABASE_USER, $DATABASE_PASSWD;
        global $DFT_PERIOD_DAY;
        // Data sets
        if (isset($_GET['d'])) {
                $dataString = $_GET['d'];
                $dataString = addslashes(strip_tags($dataString));
                $dataArray = explode(',', $dataString);
                echo "Time";
                if (in_array("rise_time",$dataArray)) {
                        echo ",Rise Time";
                }
                if (in_array("fall_time",$dataArray)) {
                        echo ",Fall time";
                }
                if (in_array("prod_time",$dataArray)) {
                        echo ",Production time (s)";
                }
                if (in_array("exp_perc",$dataArray)) {
                        echo ",Export Percentage";
                }
                if (in_array("max",$dataArray)) {
                        echo ",Daily Max (W)";
                }
                if (in_array("day",$dataArray)) {
                        echo ",Daily Energy Produced (Wh)";
                }
                if (in_array("tot_prod",$dataArray)) {
                        echo ",Total Produced (Wh)";
                }
                if (in_array("tot_ex",$dataArray)) {
                        echo ",Total Exported (Wh)";
                }
                if (in_array("tot_im",$dataArray)) {
                        echo ",Total Imported (Wh)";
                }
                echo ",\n";
                // Connect to database
                try {
                        $conn = new PDO("mysql:host=$DATABASE_ADDR;dbname=$DATABASE_DB", $DATABASE_USER, $DATABASE_PASSWD);
                }
                catch(PDOException $e) {
                        echo "Connection failed: " . $e->getMessage();
                        exit(-1);
                }
                $SQL_statement = "SELECT `epoch`";
                if ((in_array("rise_time",$dataArray)) || (in_array("prod_time",$dataArray))) {
                        $SQL_statement = $SQL_statement.",`thres_rise_epoch`";
                }
                if ((in_array("fall_time",$dataArray)) || (in_array("prod_time",$dataArray))) {
                        $SQL_statement = $SQL_statement.",`thres_fall_epoch`";
                }
                if (in_array("exp_perc",$dataArray)) {
                        $SQL_statement = $SQL_statement.",`thres_perc_exp`";
                }
                if (in_array("max",$dataArray)) {
                        $SQL_statement = $SQL_statement.",`pow_max`";
                }
                if (in_array("day",$dataArray)) {
                        $SQL_statement = $SQL_statement.",`eng_day`";
                }
                if (in_array("tot_prod",$dataArray)) {
                        $SQL_statement = $SQL_statement.",`eng_tot_prod`";
                }
                if (in_array("tot_ex",$dataArray)) {
                        $SQL_statement = $SQL_statement.",`eng_tot_out`";
                }
                if (in_array("tot_im",$dataArray)) {
                        $SQL_statement = $SQL_statement.",`eng_tot_in`";
                }
                $SQL_statement = $SQL_statement." FROM `daily`";

                $tperiod = $DFT_PERIOD_DAY*86400; // Convert default in days to seconds (60*60*24=86400)
                // Time Period (minutes)
                if (isset($_GET['tperiod'])) {
                        $period = $_GET['tperiod'];
                        if (intval($period)) {
                                //echo "period is an int";
                                $tperiod = intval($period)*86400; // Convert period from days to seconds (60*60*24=86400)
                        }
                        /*
                        else {
                                // if tperiod not givem use the default
                                echo "period is not an int";
                        }
                        */
                }

                $tstart = 0;
                // Time Start (when not auto update)
                if (isset($_GET['tstart'])) {
                        $startString = $_GET['tstart'];
                        if (intval($startString)) {
                                $tstart = intval($startString);
                        }
                }
                // Update - Get the latest data
                // If time start failed (this statement will set start time to same as if update used)
                if (isset($_GET['upd']) || ($tstart == 0)) {
                        $update = 'f';
                        if (isset($_GET['upd'])) {
                                $update = $_GET['upd'];
                        }
                        if ($update == "t" || $update == "T" || $update == "true" || $update == "True") {
                                $tstart = time()-$tperiod; // Get epoch now, and minus time period in seconds (60*60*24=86400)
                        }
                        else {
                                if (!($tstart > 0)) { // Start time wasn't set, update was false so have to use update anyway
                                        $tstart = time()-$tperiod;
                                }
                        }
                        // If set to false,
                }

                $SQL_statement = $SQL_statement." where `epoch` BETWEEN ".(string)$tstart." AND ".(string)($tstart+$tperiod)." ORDER BY `id`";
//              print $SQL_statement."\n\n";

                $data=$conn->query($SQL_statement);

                foreach ($data as $row) {
                        print date("Y-m-d H:i:s", substr($row["epoch"], 0, 10));

                        if (in_array("rise_time",$dataArray)) {
                                print ",".$row["thres_rise_epoch"];
                        }
                        if (in_array("fall_time",$dataArray)) {
                                print ",".$row["thres_fall_epoch"];
                        }
                        if (in_array("prod_time",$dataArray)) {
                                print ",".($row["thres_fall_epoch"]-$row["thres_rise_epoch"]);
                        }
                        if (in_array("exp_perc",$dataArray)) {
                                print ",".$row["thres_perc_exp"];
                        }
                        if (in_array("max",$dataArray)) {
                                print ",".$row["pow_max"];
                        }
                        if (in_array("day",$dataArray)) {
                                print ",".$row["eng_day"];
                        }
                        if (in_array("tot_prod",$dataArray)) {
                                print ",".$row["eng_tot_prod"];
                        }
                        if (in_array("tot_ex",$dataArray)) {
                                print ",".$row["eng_tot_out"];
                        }
                        if (in_array("tot_im",$dataArray)) {
                                print ",".$row["eng_tot_in"];
                        }
                        print "\r\n";


                }
                $conn = null;


        }
}

/*
 *
 * Function that deals with interval data
 *
 */
function intervalType() {
        global $DATABASE_ADDR, $DATABASE_DB, $DATABASE_USER, $DATABASE_PASSWD;
        global $DFT_PERIOD_INT;
        // Data sets
        if (isset($_GET['d'])) {
                $dataString = $_GET['d'];
                $dataString = addslashes(strip_tags($dataString));
                $dataArray = explode(',', $dataString);
                echo "Time";
                if (in_array("dc1v",$dataArray)) {
                        echo ",DC string 1 voltage (V)";
                }
                if (in_array("dc2v",$dataArray)) {
                        echo ",DC string 2 voltage (V)";
                }
                if (in_array("pf_f",$dataArray)) {
                        echo ",Power Factor at feed-in";
                }
                if (in_array("pf_i",$dataArray)) {
                        echo ",Power Factor at inverter";
                }
                if (in_array("pow_p",$dataArray)) {
                        echo ",Power Produced (W)";
                }
                if (in_array("pow_f",$dataArray)) {
                        echo ",Power at feed-in (W)";
                }
                if (in_array("use",$dataArray)) {
                        echo ",Usage (W)";
                }
                if (in_array("tot_prod",$dataArray)) {
                        echo ",Total Produced (Wh)";
                }
                if (in_array("tot_ex",$dataArray)) {
                        echo ",Total Exported (Wh)";
                }
                if (in_array("tot_im",$dataArray)) {
                        echo ",Total Imported (Wh)";
                }
                if (in_array("volt_f",$dataArray)) {
                        echo ",Voltage at Feed-in (V)";
                }
                if (in_array("cur_i",$dataArray)) {
                        echo ",Current outputted by inverter (A)";
                }
                if (in_array("freq_f",$dataArray)) {
                        echo ",Frequency at Feed (Hz)";
                }
                echo ",\n";
                // Connect to database
                try {
                        $conn = new PDO("mysql:host=$DATABASE_ADDR;dbname=$DATABASE_DB", $DATABASE_USER, $DATABASE_PASSWD);
                }
                catch(PDOException $e) {
                        echo "Connection failed: " . $e->getMessage();
                        exit(-1);
                }
                $SQL_statement = "SELECT `epoch`";
                if (in_array("dc1v",$dataArray)) {
                        $SQL_statement = $SQL_statement.",`DC_s1_v`";
                }
                if (in_array("dc2v",$dataArray)) {
                        $SQL_statement = $SQL_statement.",`DC_s2_v`";
                }
                if (in_array("pf_f",$dataArray)) {
                        $SQL_statement = $SQL_statement.",`pf_feed`";
                }
                if (in_array("pf_i",$dataArray)) {
                        $SQL_statement = $SQL_statement.",`pf_inv`";
                }
                if ((in_array("pow_p",$dataArray)) || (in_array("use",$dataArray))) {
                        $SQL_statement = $SQL_statement.",`pow_prod`";
                }
                if ((in_array("pow_f",$dataArray)) || (in_array("use",$dataArray))) {
                        $SQL_statement = $SQL_statement.",`pow_feed`";
                }
                if (in_array("tot_prod",$dataArray)) {
                        $SQL_statement = $SQL_statement.",`eng_tot_prod`";
                }
                if (in_array("tot_ex",$dataArray)) {
                        $SQL_statement = $SQL_statement.",`eng_tot_out`";
                }
                if (in_array("tot_im",$dataArray)) {
                        $SQL_statement = $SQL_statement.",`eng_tot_in`";
                }
                if (in_array("volt_f",$dataArray)) {
                        $SQL_statement = $SQL_statement.",`volt_feed`";
                }
                if (in_array("cur_i",$dataArray)) {
                        $SQL_statement = $SQL_statement.",`cur_inv`";
                }
                if (in_array("freq_f",$dataArray)) {
                        $SQL_statement = $SQL_statement.",`freq_feed`";
                }
                $SQL_statement = $SQL_statement." FROM `interval`";

                $tperiod = $DFT_PERIOD_INT*60;// Convert default in minutes to seconds
                // Time Period (minutes)
                if (isset($_GET['tperiod'])) {
                        $period = $_GET['tperiod'];
                        if (intval($period)) {
                                //echo "period is an int";
                                $tperiod = intval($period)*60;
                        }
                        /*
                         else {
                         // if tperiod not givem use the default
                         echo "period is not an int";
                         }
                         */
                }

                $tstart = 0;
                // Time Start (when not auto update)
                if (isset($_GET['tstart'])) {
                        $startString = $_GET['tstart'];
                        if (intval($startString)) {
                                $tstart = intval($startString);
                        }
                }
                // Update - Get the latest data
                // If time start failed (this statement will set start time to same as if update used)
                if (isset($_GET['upd']) || ($tstart == 0)) {
                        $update = 'f';
                        if (isset($_GET['upd'])) {
                                $update = $_GET['upd'];
                        }
                        if ($update == "t" || $update == "T" || $update == "true" || $update == "True") {
                                $tstart = time()-$tperiod; // Get epoch now, and minus time period in seconds
                        }
                        else {
                                if (!($tstart > 0)) { // Start time wasn't set, update was false so have to use update anyway
                                        $tstart = time()-$tperiod;
                                }
                        }
                        // If set to false,
                }

                $SQL_statement = $SQL_statement." where `epoch` BETWEEN ".(string)$tstart." AND ".(string)($tstart+$tperiod)." ORDER BY `epoch`";

                //echo $SQL_statement;

                $data=$conn->query($SQL_statement);

                foreach ($data as $row) {
                        print date("Y-m-d H:i:s", substr($row["epoch"], 0, 10));

                        if (in_array("dc1v",$dataArray)) {
                                print ",".$row["DC_s1_v"];
                        }
                        if (in_array("dc2v",$dataArray)) {
                                print ",".$row["DC_s2_v"];
                        }
                        if (in_array("pf_f",$dataArray)) {
                        	if (floatval($row["pf_feed"]) >= 0) { print ",".strval(rad2deg(acos(floatval($row["pf_feed"])))); }
                        	else { print ",".strval(rad2deg(acos(floatval($row["pf_feed"])))-180); }
                        }
                        if (in_array("pf_i",$dataArray)) {
                        	if (floatval($row["pf_inv"]) >= 0) { print ",".strval(rad2deg(acos(floatval($row["pf_inv"])))); }
                        	else { print ",".strval(rad2deg(acos(floatval($row["pf_inv"])))-180); }
                        }
                        if (in_array("pow_p",$dataArray)) {
                                print ",".$row["pow_prod"];
                        }
                        if (in_array("pow_f",$dataArray)) {
                                print ",".$row["pow_feed"];
                        }
                        if (in_array("use",$dataArray)) {
                                print ",".($row["pow_feed"]+$row["pow_prod"]);
                        }
                        if (in_array("tot_prod",$dataArray)) {
                                print ",".$row["eng_tot_prod"];
                        }
                        if (in_array("tot_ex",$dataArray)) {
                                print ",".$row["eng_tot_out"];
                        }
                        if (in_array("tot_im",$dataArray)) {
                                print ",".$row["eng_tot_in"];
                        }
                        if (in_array("volt_f",$dataArray)) {
                                print ",".$row["volt_feed"];
                        }
                        if (in_array("cur_i",$dataArray)) {
                                print ",".$row["cur_inv"];
                        }
                        if (in_array("freq_f",$dataArray)) {
                                print ",".$row["freq_feed"];
                        }
                        print "\r\n";


                }
                $conn = null;
        }
}
?>
