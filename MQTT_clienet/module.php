<?
/*
  MQTT Module IPSymcon PHP Splitter Module Class
  uses TFphpMQTT.php
  author Thomas Feldmann
  copyright Thomas Feldmann 2017
  version 0.1.0
  date 2017-03-18
 */
    include_once(__DIR__ . "/../module_helper.php");
    include_once(__DIR__ . "/TFphpMQTT.php");
    
    // Klassendefinition
    class MQTTClient extends T2FModule {
        //------------------------------------------------------------------------------
        //module const and vars
        //------------------------------------------------------------------------------
        /**
         * MQTT QOS constant "At Most once" (Fire and forget)
         * Used here for publishing, no need to take care
         * @see http://www.hivemq.com/blog/mqtt-essentials-part-6-mqtt-quality-of-service-levels
         */
        const MQTT_QOS_0_AT_MOST_ONCE=0;

        /**
         * MQTT QOS setting
         * set to QOS=0 because we are publisher only
         * @var int $qos
         */
        private $qos=self::MQTT_QOS_0_AT_MOST_ONCE;
        /**
         * MQTT Retain setting
         * @var boolean $retained
         */
        private $retained=false;
        
        private $mqtt;
        
        // Der Konstruktor des Moduls
        public function __construct($InstanceID)
        {
            // Diese Zeile nicht löschen
            $json = __DIR__ . "/module.json";
            parent::__construct($InstanceID, $json);

            $sClass = $this->GetBuffer("MQTT");
            IF ($sClass <> ""){
                $this->mqtt = unserialize($sClass);
            }else{
                $this->mqtt = NULL;
            }
            $this->conecting = false;
            
           
        }
 
        // Überschreibt die interne IPS_Create($id) Funktion
        public function Create() {
            // Diese Zeile nicht löschen.
            parent::Create();
 
            // Selbsterstellter Code
            $this->RequireParent("{3CFF0FD9-E306-41DB-9B5A-9D06D38576C3}");    //Client Socet Modul
            $this->RegisterPropertyString('ClientID', 'symcon');
            $this->RegisterPropertyString('User', '');
            $this->RegisterPropertyString('Password', '');
            $this->RegisterPropertyBoolean('Active', false);
            $this->RegisterPropertyInteger('script', 0);

            //register status msg
            $this->RegisterMessage(0, self::IPS_KERNELMESSAGE );

        }
 
        //--------------------------------------------------------
        /**
         * overwrite internal IPS_ApplChanges($id) function
         */
        public function ApplyChanges()
        {
            $this->RegisterMessage(0, self::IPS_KERNELMESSAGE );
            $cID = $this->GetConnectionID();
            if($cID <> 0){
                $this->RegisterMessage($cID,self::IM_CHANGESTATUS);
            }
            // Diese Zeile nicht loeschen
            parent::ApplyChanges();
            if ($this->isActive()) {
                $this->SetStatus(self::ST_AKTIV);
                $this->debug(__FUNCTION__,"Modul Akteviert");  // Debug Fenster
                $this->MQTTConnect();
            } else {
                $this->SetStatus(self::ST_INACTIV);
                $this->debug(__FUNCTION__,"Modul deaktiviert");  // Debug Fenster
                $this->MQTTDisconnect(1);
            }
            
        }
 
        //------------------------------------------------------------------------------
       /**
        * Get Property category name to be created and used for Device Instnaces
        * @return string
        */
       private function GetUser()
       {
           return (String)IPS_GetProperty($this->InstanceID, 'User');
       }

        //------------------------------------------------------------------------------
       /**
        * Get Property category name to be created and used for Device Instnaces
        * @return string
        */
       private function GetPassword()
       {
           return (String)IPS_GetProperty($this->InstanceID, 'Password');
       }
        
        //------------------------------------------------------------------------------
        /**
         * Get Property Port
         * @return string
         */
        private function GetClientID()
        {
            $clientid=(String)IPS_GetProperty($this->InstanceID, 'ClientID');
            $clientid.="@".gethostname();
            return $clientid;
        }

        /**
         * Get Property Port
         * @return string
         */
        private function GetConnectionID()
        {
            $data=IPS_GetInstance($this->InstanceID);
            return $data['ConnectionID'];
        }

        
        
    //------------------------------------------------------------------------------
    //---Events
    //------------------------------------------------------------------------------

        /**
         * Handle Message Events
         * will be called from IPS message loop for registered objects and events
         *
         * @param int $TimeStamp Timestamp of Event (looks not filled)
         * @param int $SenderID related object ID
         * @param int $Message related Message ID
         * @param array $Data Payload (content depends on Message ID)
         *
         *  @see https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/nachrichten/
         */
        public function MessageSink($TimeStamp, $SenderID, $Message, $Data) {
//            $this->debug(__FUNCTION__,"entered");
            $id=$SenderID;
            $this->debug(__FUNCTION__, "TS: $TimeStamp SenderID ".$SenderID." with MessageID ".$Message." Data: ".print_r($Data, true));
            switch ($Message) {
                case self::VM_UPDATE:
                    $this->Publish($id,$Data);
                    break;
                case self::VM_DELETE:
                    $this->UnSubscribe($id);
                    break;
                case self::IM_CHANGESTATUS:
                    switch ($Data[0]) {
                        case self::ST_AKTIV:
                            $this->debug(__CLASS__,__FUNCTION__."I/O Modul > Aktiviert");
                           // $this->MQTTDisconnect(2);
                            break;
                        case self::ST_INACTIV:
                            $this->debug(__CLASS__,__FUNCTION__."I/O Modul > Deaktiviert");
                            //$this->MQTTDisconnect(3);
                            break;
                        case self::ST_ERROR_0:
                            $this->debug(__CLASS__,__FUNCTION__."I/O Modul > Fehler");
                            $this->MQTTDisconnect(4);
                            break;
                        default:
                            IPS_LogMessage(__CLASS__,__FUNCTION__."I/O Modul unbekantes Ereignis ".$Data[0]);                     
                            break;
                    }
                    break;
                case self::IPS_KERNELMESSAGE:
                    $kmsg=$Data[0];
                    switch ($kmsg) {

                        case self::KR_READY:
                            IPS_LogMessage(__CLASS__,__FUNCTION__." KR_Ready ->reconect");
                           // $this->MQTTDisconnect(5);
                            break;
 /*
                        case self::KR_UNINIT:
                            // not working :(
                            $msgid=$this->GetBuffer("MsgID");
                            IPS_SetProperty($this->InstanceID,'MsgID',(Integer)$msgid);
                            IPS_ApplyChanges($this->InstanceID);
                            IPS_LogMessage(__CLASS__,__FUNCTION__." KR_UNINIT ->disconnect()");
                            break;  */
                        
                        default:
                            IPS_LogMessage(__CLASS__,__FUNCTION__." Kernelmessage unhahndled, ID".$kmsg);
                            break;
                    }
                    break;
                default:
                    IPS_LogMessage(__CLASS__,__FUNCTION__." Unknown Message $Message");
                    break;
            }
//            $this->debug(__FUNCTION__,"leaved");

        }
        
        //------------------------------------------------------------------------------
        //Data Interfaces to parant
        //------------------------------------------------------------------------------

        /**
         * Data Interface from Parent(IO-RX)
         * @param string $JSONString
         */
        public function ReceiveData($JSONString)
        {
            if (!is_null($this->mqtt)){
            
                //status check triggered by data
                if ($this->isActive()) {
                    $this->SetStatus(self::ST_AKTIV);
                } else {
                    $this->SetStatus(self::ST_INACTIV);
                    $this->debug(__FUNCTION__, 'Data arrived, but dropped because inactiv:' . $JSONString);
                    return;
                }
                // decode Data from Device Instanz
                if (strlen($JSONString) > 0) {
                    $this->debug(__FUNCTION__, 'Data arrived:' . $JSONString);
                    $this->debuglog($JSONString);
                    // decode Data from IO Instanz
                    $data = json_decode($JSONString);
                    //entry for data from parent

                    if (is_object($data)) { $data = get_object_vars($data);}
                    if (isset($data['DataID'])) {
                        $target = $data['DataID'];
                        if ($target == $this->module_interfaces['IO-RX']) {
                            $buffer = utf8_decode($data['Buffer']);
                            $this->debug(__FUNCTION__, strToHex($buffer));
                            $this->mqtt->receive($buffer);
                            $sClass = serialize($this->mqtt);
                            $this->SetBuffer ("MQTT",$sClass);   
                            // Ping Timer neu setzen
                            // $this->RegisterTimerNow('Ping', $this->mqtt->keepalive*1000,  'MQTT_TimerEvent('.$this->InstanceID.');');                            
                        }//target
                    }//dataid
                    else {
                        $this->debug(__FUNCTION__, 'No DataID supplied');
                    }//dataid
                } else {
                    $this->debug(__FUNCTION__, 'strlen(JSONString) == 0');
                }//else len json
            }else{
                $this->debug(__FUNCTION__, '$this->mqtt == null');                
            }
        }//func
 
        /**
         * Data Interface tp Parent (IO-TX)
         * Forward commands to IO Instance
         * @param String $Data
         * @return bool
         */
        public function onSendText($Data)
        {
            $res = false;
            $json = json_encode(
                array("DataID" => $this->module_interfaces['IO-TX'],
                    "Buffer" => utf8_encode($Data)));
            if ($this->HasActiveParent()) {
                $this->debug(__FUNCTION__, strToHex($Data));
                $res = parent::SendDataToParent($json);
            } else {
                $this->debug(__FUNCTION__, 'No Parent');
            }
            $this->RegisterTimerNow('Ping', $this->mqtt->keepalive*1000,  'MQTT_TimerEvent('.$this->InstanceID.');');                            
            return $res;

        }//function
        public function onDebug($topic, $data) {
            $this->debug($topic, $data);
        }
 
        public function onReceive($para) {
            if($para['SENDER']=='MQTT_CONNECT'){
                $clientid=$this->GetClientID();                               
                IPS_LogMessage(__CLASS__,__FUNCTION__."::Connection to ClientID $clientid run");                
            }
            $scriptid = $this->ReadPropertyInteger("script");
            IPS_RunScriptEx($scriptid,$para);
        }
        
        //------------------------------------------------------------------------
        //
        //------------------------------------------------------------------------
        public function TimerEvent() {
            $this->debug(__FUNCTION__,"Timer");  // Debug Fenster            // Selbsterstellter Code
            if (!is_null($this->mqtt)){
               $this->mqtt->ping();
            }
        }        
        //------------------------------------------------------------------------
        //
        //------------------------------------------------------------------------
        public function Publish($topic, $content, $qos = 0, $retain = 0) {
            if (!is_null($this->mqtt)){
                $this->mqtt->publish($topic, $content, $qos, $retain);
            }else {
                $this->debug(__FUNCTION__,"Error, Publish nicht möglich");               
            }
        }     
        
        //------------------------------------------------------------------------
        //
        //------------------------------------------------------------------------
        public function Subscribe($topic, $qos = 0) {
            if (!is_null($this->mqtt)){
                $this->mqtt->subscribe($topic, $qos);
            }else {
                $this->debug(__FUNCTION__,"Error, Subscribe nicht möglich");               
            }
        }     
       
        //------------------------------------------------------------------------
        //
        //------------------------------------------------------------------------
        private function MQTTConnect(){
            IPS_LogMessage(__CLASS__,__FUNCTION__."::Connect to cliend start");
            IPS_Sleep(1500);
            $cID=$this->GetConnectionID();
            if(is_null($this->mqtt)){
                $ok = @IPS_SetProperty($cID, "Open", true); //I/O Instanz soll aktiviert sein.
                if($ok){
                $ok = @IPS_ApplyChanges($cID); //Neue Konfiguration übernehmen                    
                    $clientid=$this->GetClientID();               
                    if ($ok) {
                        $username=$this->GetUser();
                        $password=$this->GetPassword();
                        $owner = $this; 
                        $this->mqtt = new phpMQTT($owner,$clientid);
                        // callback Funktionen
                        $this->mqtt->onSend = 'onSendText';
                        $this->mqtt->onDebug = 'onDebug';
                        $this->mqtt->onReceive = 'onReceive';
                        $this->mqtt->debug = true;  
                        if ($this->mqtt -> connect(true,null,$username,$password)) {
                            $this->debug(__FUNCTION__,"Connected to ClientID $clientid");
                            $this->OSave($this->mqtt,"MQTT");
                            $this->RegisterTimerNow('Ping', $this->mqtt->keepalive*1000,  'MQTT_TimerEvent('.$this->InstanceID.');');    
                        }else{
                            $ok = FALSE;
                        }
                    }else{
                        IPS_LogMessage(__CLASS__,__FUNCTION__."::Connect to ClientID $clientid failed");
                        $this->debug(__FUNCTION__,"Connect to ClientID $clientid failed");
                        IPS_SetProperty($cID, "Open", false);
                        IPS_ApplyChanges($cID); //Neue Konfiguration übernehmen                    
                    }
                }
            }
        }
        //------------------------------------------------------------------------
        //
        //------------------------------------------------------------------------
        private function MQTTDisconnect($call=""){
            if(!is_null($this->mqtt)){
                $this->mqtt->close();
                $this->mqtt = NULL;
                $this->OSave($this->mqtt,"MQTT");
                $clientid = $this->GetClientID();
                IPS_LogMessage(__CLASS__,__FUNCTION__."::Connection to ClientID $clientid lost ($call)");
            }
            $cID=$this->GetConnectionID();
            if($cID <> 0){
                IF (IPS_GetProperty($cID,"Open")){
                    IPS_SetProperty($cID, "Open", FALSE); //I/O Instanz soll aktiviert sein.
                    IPS_ApplyChanges($cID); //Neue Konfiguration übernehmen  
                }                                              
            }
            $this->RegisterTimerNow('Ping', 0,  'MQTT_TimerEvent('.$this->InstanceID.');');    
            if($this->GetInstanceStatus() == self::ST_AKTIV ){       
                $this->MQTTConnect();
            }
        }
        //------------------------------------------------------------------------
        //
        //------------------------------------------------------------------------
        private function OSave($object,$name){
            if($object === NULL){
                $sClass = "";
            }else{
                $sClass = serialize($object);
            }
            $this->SetBuffer ($name,$sClass);    
        }
    }
?>