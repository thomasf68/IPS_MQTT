<?php

/*
	modified phpMQTT class
 
	author Blue Rhinos Consulting, modified by Thomas Dressler
	copyright 2010 Blue Rhinos Consulting | Andrew Milsted
	copyright Thomas Feldmann
	version 0.1.0
	date 2017-03-19
	
 */


/*
 	phpMQTT
	A simple php class to connect/publish/subscribe to an MQTT broker
 
*/

/*
	Licence

	Copyright (c) 2010 Blue Rhinos Consulting | Andrew Milsted
	andrew@bluerhinos.co.uk | http://www.bluerhinos.co.uk

	Permission is hereby granted, free of charge, to any person obtaining a copy
	of this software and associated documentation files (the "Software"), to deal
	in the Software without restriction, including without limitation the rights
	to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
	copies of the Software, and to permit persons to whom the Software is
	furnished to do so, subject to the following conditions:

	The above copyright notice and this permission notice shall be included in
	all copies or substantial portions of the Software.

	THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
	IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
	FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
	AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
	LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
	OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
	THE SOFTWARE.
	
*/

/* phpMQTT */
class phpMQTT {

	private $msgid = 1;			/* counter for message id */
	public $keepalive = 60;		/* default keepalive timmer */
	public $topics = array(); 	/* used to store currently subscribed topics */
	public $clientid;			/* client id sent to brocker, string */
	public $will;				/* stores the will of the client */
	private $username;			/* stores username */
	private $password;			/* stores password */
        public $onSend;   /* Function to send */
        public $onDebug = "";
        public $onReceive = "";
	public $debug = false;		/* should output debug messages */
	public $status;          /* Verbindungsstaus */
        private $buffer = "";

	function __construct($owner,$clientid){
		$this->broker($clientid);
                $this->owner = $owner;  
                }

	/* sets the broker details */
	function broker($clientid){
		$this->clientid = $clientid;		
	}

	/* connects to the broker 
		inputs: $clean: should the client send a clean session flag */
	function connect($clean = true, $will = NULL, $username = NULL, $password = NULL){
		
		$this->status = 1;
		
		if($will) $this->will = $will;
		if($username) $this->username = $username;
		if($password) $this->password = $password;

		$i = 0;
		$buffer = "";

		$buffer .= chr(0x00); $i++;
		$buffer .= chr(0x06); $i++;
		$buffer .= chr(0x4d); $i++;   // M
		$buffer .= chr(0x51); $i++;   // Q
		$buffer .= chr(0x49); $i++;   // I
		$buffer .= chr(0x73); $i++;   // s
		$buffer .= chr(0x64); $i++;   // d
		$buffer .= chr(0x70); $i++;   // p
		$buffer .= chr(0x03); $i++;   // Version

		//No Will
		$var = 0;
		if($clean) $var+=2;

		//Add will info to header
		if($this->will != NULL){
			$var += 4; // Set will flag
			$var += ($this->will['qos'] << 3); //Set will qos
			if($this->will['retain'])	$var += 32; //Set will retain
		}

		if($this->username != NULL) $var += 128;	//Add username to header
		if($this->password != NULL) $var += 64;	//Add password to header

		$buffer .= chr($var); $i++;

		//Keep alive
		$buffer .= chr($this->keepalive >> 8); $i++;
		$buffer .= chr($this->keepalive & 0xff); $i++;

		$buffer .= $this->strwritestring($this->clientid,$i);

		//Adding will to payload
		if($this->will != NULL){
			$buffer .= $this->strwritestring($this->will['topic'],$i);  
			$buffer .= $this->strwritestring($this->will['content'],$i);
		}

		if($this->username) $buffer .= $this->strwritestring($this->username,$i);
		if($this->password) $buffer .= $this->strwritestring($this->password,$i);

		$head = "  ";
		$head{0} = chr(0x10);
		$head{1} = chr($i);

                // Sende Daten
//		fwrite($this->socket, $head, 2);
		$call = $this->onSend;
                $this->owner->$call($head);

//		fwrite($this->socket,  $buffer);
		$this->owner->$call($buffer);

		return true;
	}

	// Bearbeiten Empfangender Daten

	function receive($string_in = NULL){
            $this->buffer .= $string_in;
            
            do {
                $bOk = TRUE;    
                $buffer_old = $this->buffer; 

                $byte1 = $this->read(1); 
                // Wennn nicht genug Byte im Puffer abbrechen
                if($byte1=== false){
                    $bOk = false;
                    break;
                } 
                
                $cmd = ord($byte1)>>4;

                $multiplier = 1; 
                $value = 0;
                do{
                        $digit = ord($this->read(1));
                        // Wennn nicht genug Byte im Puffer abbrechen
                        if($byte1=== false){  
                            $bOk = false;
                            break;
                         } 
                        $value += ($digit & 127) * $multiplier; 
                        $multiplier *= 128;
                }while (($digit & 128) != 0);

                if($this->debug){
                    $call = $this->onDebug;
                    $this->owner->$call(__FUNCTION__,"Fetching: $value ");
                }

                if($value){
                    $string = $this->read($value);
                    // Wennn nicht genug Byte im Puffer abbrechen                        
                    if($byte1=== false){  
                        $bOk = false;
                        break;
                     } 
                    if($this->debug){
                        $call = $this->onDebug;
                        $this->owner->$call(__FUNCTION__,"Fetching: ".strToHex($string));
                    }                         
                }
                
                switch($cmd){
                    case 2:         // CONNACK, Connect acknowledgment
                        if ($string{1} == chr(0)){
                            if($this->debug) {
                                $call = $this->onDebug;
                                $this->owner->$call(__FUNCTION__,"Connected to broker ok");
                            }
                            $this->status = 2;   // Staus Verbunden
                            // callback
                            $para = Array("SENDER" => "MQTT_CONNECT");
                            $call = $this->onReceive;
                            $this->owner->$call($para);
                        } 
                        break;
                    case 3: // PUBLISH, Publish message
                                $this->message($string);
                        break;
                    case 13:         // PINGRESP, PING response
                        if($this->debug) {
                            $call = $this->onDebug;
                            $this->owner->$call(__FUNCTION__,"PING response");
                        }
                        break;
                    default:    
                        if($this->debug) {
                            $call = $this->onDebug;
                            $this->owner->$call(__FUNCTION__,sprintf("unexpected response cmd: 0x%01X",$cmd));
                        }
                    }
                
            } while ($bOk & strlen($this->buffer)> 0);
            
            // Auswertung Abgebochen ! nich ganz ausgewertete Bytes wieder in den Puffer Schreiben
            if (!$bOk){
                $this->buffer = $buffer_old;
            }
            
            if (strlen($this->buffer)> 0){
                $call = $this->onDebug;
                $this->owner->$call(__FUNCTION__,"Buffer nicht leer");
            }
                        
        }         
        
        /* read: reads in so many bytes NoOf => Anzahl byte, nb => nicht blockierend, 
        $nb = true  => es wird maximal soviel zurückgegeben wie vorhanden
        $nb = false => wenn nicht genug vorhanden wird False zurückgegeben    
        */
        function read($NoOf = 8192, $nb = false){
		
            $togo = $NoOf;
            $return = false;

            if($nb){
                $togo = strlen($this->buffer);
            }

            $int = strlen($this->buffer) - $NoOf;
            if ($int >= 0){ 
                $return = substr($this->buffer,0,$NoOf);
                $this->buffer = substr($this->buffer,$NoOf);
            }       

            return $return;
	}

	/* subscribe: subscribes to topics */
	function subscribe($topic = "", $qos = 0){
		$i = 0;
		$buffer = "";
		$id = $this->msgid;
		$buffer .= chr($id >> 8);  $i++;
		$buffer .= chr($id % 256);  $i++;

                $buffer .= $this->strwritestring($topic,$i);
                $buffer .= chr($qos);  $i++;

    		$cmd = 0x80;  // => 'subscribe'
		//$qos
		$cmd +=	($qos << 1);

		$head = chr($cmd);
                $head .= $this->setmsglength($i);
		
                $call = $this->onSend;
                $this->owner->$call($head);
		$this->owner->$call($buffer);
                
	}
               
	/* ping: sends a keep alive ping */
	function ping(){
			$head = chr(0xc0).chr(0x00);		
			//fwrite($this->socket, $head, 2);
                        $call = $this->onSend;
                        $this->owner->$call($head);
                        //if($this->debug) echo "ping sent\n";
	}

	/* disconnect: sends a proper disconect cmd */
	function disconnect(){
			$head = " ";
			$head{0} = chr(0xe0);		
			$head{1} = chr(0x00);
			//fwrite($this->socket, $head, 2);
                        $call = $this->onSend;
                        $this->owner->$call($head);
        }

	/* close: sends a proper disconect, then closes the socket */
	function close(){
	 	$this->disconnect();
	}

	/* publish: publishes $content on a $topic */
	function publish($topic, $content, $qos = 0, $retain = 0){

            $i = 0;
            $buffer = "";

            $buffer .= $this->strwritestring($topic,$i);

            if($qos){
                    $id = $this->msgid++;
                    $buffer .= chr($id >> 8);  $i++;
                    $buffer .= chr($id % 256);  $i++;
            }

            $buffer .= $content;
            $i+=strlen($content);

            $head = " ";
            $cmd = 0x30;

            if($qos){
                $cmd += $qos << 1;                
            }
            if($retain){
                $cmd += 1;
            }

            $head{0} = chr($cmd);		
            $head .= $this->setmsglength($i);

            $call = $this->onSend;
            $this->owner->$call($head.$buffer);
           
	}

	/* message: processes a recieved topic */
	function message($msg){
            $tlen = (ord($msg{0})<<8) + ord($msg{1});
            $topic = substr($msg,2,$tlen);
            $msg = substr($msg,($tlen+2));
            $cmd = "MQTT_GET_PAYLOAD";
            
            if($this->debug){
                $call = $this->onDebug;
                $this->owner->$call(__FUNCTION__,"Topic: $topic, Msg: $msg");
            }    

            // callback
            $para = Array("TOPIC" => $topic, "MSG" => $msg,"SENDER" => $cmd);
            $call = $this->onReceive;
            $this->owner->$call($para);
	}

	/* setmsglength: */
	function setmsglength($len){
		$string = "";
		do{
		  $digit = $len % 128;
		  $len = $len >> 7;
		  // if there are more digits to encode, set the top bit of this digit
		  if ( $len > 0 )
		    $digit = ($digit | 0x80);
		  $string .= chr($digit);
		}while ( $len > 0 );
		return $string;
	}

	/* strwritestring: writes a string to a buffer */
	function strwritestring($str, &$i){
		$ret = " ";
		$len = strlen($str);
		$msb = $len >> 8;
		$lsb = $len % 256;
		$ret = chr($msb);
		$ret .= chr($lsb);
		$ret .= $str;
		$i += ($len+2);
		return $ret;
	}
}

?>
