<?


    //IPS_LogMessage(IPS_GetName($_IPS['SELF'])." ".$_IPS['SELF'],print_r($_IPS,true));

    if($_IPS['SENDER']=='MQTT_GET_PAYLOAD'){

            $msg = $_IPS['MSG'];
            $topic = explode("/", $_IPS['TOPIC']);
            $id_parent = 0;
            $last = count($topic)-1;
            $type = $topic[$last];

            switch ($type) {
                    case '0':
                    case '1':
                    case '2':
                    case '3':
                            foreach($topic as $i => $vname){
                                    $id = @IPS_GetObjectIDByName($vname, $id_parent);
                                    if($id===false){
                                    if($last== $i){
                                                    $id= $id_parent;
                            }elseif($last -1 == $i){
                                    $id = IPS_CreateVariable($type);      
                                    IPS_SetName($id, $vname); 
                                    IPS_SetParent($id, $id_parent);
                                }else{
                                    $id = IPS_CreateCategory();      
                                    IPS_SetName($id, $vname); 
                                    IPS_SetParent($id, $id_parent);
                                }
                                    }
                                    $id_parent = $id;
                            }
                            SetValue($id,$msg); 
                            break;
                    default:
                            IPS_LogMessage(IPS_GetName($_IPS['SELF'])." ".$_IPS['SELF'],"Topic '".$_IPS['TOPIC']. "' endhält keine Typinfo");		
            }

    }

    if($_IPS['SENDER']=='MQTT_CONNECT'){
            $topic = "#";														
            MQTT_Subscribe(41440 /*[MQTT Client]*/, $topic, 0);	  // ID Anpassen !!!
    }


?>