<?

	# IPS_LogMessage(IPS_GetName($_IPS['SELF'])." ".$_IPS['SELF'],print_r($_IPS,true));

	if($_IPS['SENDER']=='Variable'){
		$id = $_IPS['VARIABLE'];
		$topic = "RPI_Schlafen/".create_path($id);
		$msg = $_IPS['VALUE']; 
		if($msg===false){$msg = 'false';}
		elseif($msg===true){$msg = 'true';}
		$info = IPS_GetVariable($_IPS['VARIABLE']);
		$type = $info['VariableType'];
		$topic .= '/'.$type; 
		MQTT_Publish(33877 /*[MQTT Client]*/, $topic,$msg,0,0);
	}

    function create_path ($id) {
        $path='';
        do {

            $obj=IPS_GetObject($id);
            $name=$obj['ObjectName'];
            $path=$name."/".$path;
            $id=IPS_GetParent($id);

        } while ($id>0);
		$path = substr($path,0,-1);  // letztes '/' endfernen
        return $path;
    }


?>