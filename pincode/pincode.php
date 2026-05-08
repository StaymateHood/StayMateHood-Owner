<?php

function pincodecheck($pin, $state) {
    global $conn; // Don't forget this if using $con inside function

    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => "https://api.postalpincode.in/pincode/$pin",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'GET',
        CURLOPT_SSL_VERIFYPEER => false,
    ));
    
    $response = curl_exec($curl);
    curl_close($curl); 
    
    $responselist = json_decode($response);
    
    if ($responselist[0]->Status == 'Success') {
        $data = [];
        foreach ($responselist[0]->PostOffice as $po) {
            $data[] = $po->Name;
        }

        $pinval = false;
        if (is_numeric($state)) {
            $pinval = checkPincodeRangeForGivenState($state, $pin);
        } else {
            $query = $conn->query("SELECT * FROM STATES_AND_UT_ENUM WHERE STATE_OR_UT_NAME = '$state'");
            if ($query && $query->num_rows > 0) {
                $row = $query->fetch_assoc();
                $pinval = checkPincodeRangeForGivenState($row['STATE_OR_UT_CODE'], $pin);
            }
        }

        if (!$pinval) {
            return ['status' => 1, 'data' => $data]; // Valid pin and state
        } else {
            return ['status' => 2, 'data' => $data]; // Pin exists, but state mismatch
        }
    } else {
        return ['status' => 0, 'data' => []]; // Invalid pin
    }
}


function checkPincodeRangeForGivenState($state_code,$b_cvo_pincode){
        //alert("$state_code:"+$state_code);
         $state_code;
         $b_cvo_pincode_3digts = substr($b_cvo_pincode, 0,3);
        $flag = true;
        if($state_code==01){
                if($b_cvo_pincode_3digts>=180 && $b_cvo_pincode_3digts<195){
                        $flag = false;
                        return $flag;
                }
        }else if($state_code==02){
                if($b_cvo_pincode_3digts>=171 && $b_cvo_pincode_3digts<178){
                        $flag = false;
                        return $flag;
                }
        }else if($state_code==03){
                if($b_cvo_pincode_3digts>=140 && $b_cvo_pincode_3digts<161){
                        $flag = false;
                        return $flag;
                }
        }else if($state_code==04){
                if($b_cvo_pincode_3digts>=160 && $b_cvo_pincode_3digts<161){
                        $flag = false;
                        return $flag;
                }
        }else if($state_code==05){
                if($b_cvo_pincode_3digts>=244 && $b_cvo_pincode_3digts<264){
                        $flag = false;
                        return $flag;
                }
        }else if($state_code==06){
                if($b_cvo_pincode_3digts>=121 && $b_cvo_pincode_3digts<137){
                        $flag = false;
                        return $flag;
                }
        }else if($state_code=='07'){
                if($b_cvo_pincode_3digts>=110 && $b_cvo_pincode_3digts<111){
                        $flag = false;
                        return $flag;
                }
        }else if($state_code=='08'){
                if($b_cvo_pincode_3digts>=301 && $b_cvo_pincode_3digts<346){
                        $flag = false;
                        return $flag;
                }
        }else if($state_code=='09'){
                if($b_cvo_pincode_3digts>=201 && $b_cvo_pincode_3digts<286){
                        $flag = false;
                        return $flag;
                }
        }else if($state_code=='10'){
                if($b_cvo_pincode_3digts>=800 && $b_cvo_pincode_3digts<856){
                        $flag = false;
                        return $flag;
                }
        }else if($state_code=='11'){
                if($b_cvo_pincode_3digts>=737 && $b_cvo_pincode_3digts<738){
                        $flag = false;
                        return $flag;
                }
        }else if($state_code=='12'){
                if($b_cvo_pincode_3digts>=790 && $b_cvo_pincode_3digts<793){
                        $flag = false;
                        return $flag;
                }
        }else if($state_code=='13'){
                if($b_cvo_pincode_3digts>=797 && $b_cvo_pincode_3digts<799){
                        $flag = false;
                        return $flag;
                }
        }else if($state_code==14){
                if($b_cvo_pincode_3digts>=795 && $b_cvo_pincode_3digts<796){
                        $flag = false;
                        return $flag;
                }
        }else if($state_code==15){
                if($b_cvo_pincode_3digts>=796 && $b_cvo_pincode_3digts<797){
                        $flag = false;
                        return $flag;
                }
        }else if($state_code==16){
                if($b_cvo_pincode_3digts>=799 && $b_cvo_pincode_3digts<800){
                        $flag = false;
                        return $flag;
                }
        }else if($state_code==17){
                if($b_cvo_pincode_3digts>=793 && $b_cvo_pincode_3digts<795){
                        $flag = false;
                        return $flag;
                }
        }else if($state_code==18){
                if($b_cvo_pincode_3digts>=781 && $b_cvo_pincode_3digts<789){
                        $flag = false;
                        return $flag;
                }
        }else if($state_code==19){
                if($b_cvo_pincode_3digts>=700 && $b_cvo_pincode_3digts<744){
                        $flag = false;
                        return $flag;
                }
        }else if($state_code==20){
                if($b_cvo_pincode_3digts>=813 && $b_cvo_pincode_3digts<836){
                        $flag = false;
                        return $flag;
                }
        }else if($state_code==21){
                if($b_cvo_pincode_3digts>=751 && $b_cvo_pincode_3digts<771){
                        $flag = false;
                        return $flag;
                }
        }else if($state_code==22){
                if($b_cvo_pincode_3digts>=490 && $b_cvo_pincode_3digts<498){
                        $flag = false;
                        return $flag;
                }
        }else if($state_code==23){
                if($b_cvo_pincode_3digts>=450 && $b_cvo_pincode_3digts<488){
                        $flag = false;
                        return $flag;
                }
        }else if($state_code==24){
                if($b_cvo_pincode_3digts>=360 && $b_cvo_pincode_3digts<396){
                        $flag = false;
                        return $flag;
                }
        }else if($state_code==25){
                if($b_cvo_pincode_3digts>=362 && $b_cvo_pincode_3digts<363){
                        $flag = false;
                        return $flag;
                }
                if($b_cvo_pincode_3digts>=396 && $b_cvo_pincode_3digts<397){
                        $flag = false;
                        return $flag;
                }
        }else if($state_code==26){
                if($b_cvo_pincode_3digts>=396 && $b_cvo_pincode_3digts<397){
                        $flag = false;
                        return $flag;
                }
        }else if($state_code==27){
                if($b_cvo_pincode_3digts>=400 && $b_cvo_pincode_3digts<446){
                        $flag = false;
                        return $flag;
                }
        }else if($state_code==29){
                if($b_cvo_pincode_3digts>=560 && $b_cvo_pincode_3digts<592){
                        $flag = false;
                        return $flag;
                }
        }else if($state_code==30){
                if($b_cvo_pincode_3digts>=403 && $b_cvo_pincode_3digts<404){
                        $flag = false;
                        return $flag;
                }
        }else if($state_code==31){
                if($b_cvo_pincode_3digts>=682 && $b_cvo_pincode_3digts<683){
                        $flag = false;
                        return $flag;
                }
        }else if($state_code==32){
                if($b_cvo_pincode_3digts>=670 && $b_cvo_pincode_3digts<696){
                        $flag = false;
                        return $flag;
                }
        }else if($state_code==33){
                if($b_cvo_pincode_3digts>=600 && $b_cvo_pincode_3digts<644){
                        $flag = false;
                        return $flag;
                }
        }else if($state_code==34){
                if($b_cvo_pincode_3digts>=533 && $b_cvo_pincode_3digts<534){
                        $flag = false;
                        return $flag;
                }if($b_cvo_pincode_3digts>=605 && $b_cvo_pincode_3digts<606){
                        $flag = false;
                        return $flag;
                }if($b_cvo_pincode_3digts>=607 && $b_cvo_pincode_3digts<608){
                        $flag = false;
                        return $flag;
                }if($b_cvo_pincode_3digts>=609 && $b_cvo_pincode_3digts<610){
                        $flag = false;
                        return $flag;
                }
        }else if($state_code==35){
                if($b_cvo_pincode_3digts>=744 && $b_cvo_pincode_3digts<745){
                        $flag = false;
                        return $flag;
                }
        }else if($state_code==36){
                if($b_cvo_pincode_3digts>=500 && $b_cvo_pincode_3digts<510){
                        $flag = false;
                        return $flag;
                }
        }else if($state_code==37){
                if($b_cvo_pincode_3digts>=500 && $b_cvo_pincode_3digts<536){
                        $flag = false;
                        return $flag;
                }
        }
        return $flag;
}
?>