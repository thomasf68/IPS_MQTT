# IPS_MQTT
MQTT Modul für IP-Symcon 4.1

Das Modul unterstützt das minimal Nötige um über MQTT Daten Sendent und Empangen zu können.


Befehle:
    
    MQTT_Subscribe($topic, $qos = 0)

    MQTT_Publish($topic, $content, $qos = 0, $retain = 0)

Ereignisse:

    MQTT_GET_PAYLOAD

        Es wurden Nutzdaten Empfangen

    MQTT_CONNECT

        Ein Connect mit dem MQTT Broaker ist abgeschlossen
        
Unter /IPS Scripte/MQTT_clienet/Handel.php liegt ein Beispiel wie auf Ereignisse von dem Modul reagiert werden kann.
Das Script 'Publish.php' benutze ich um veränderte Variablen aus IPS zu senden.
Mit den beiden Beispiel Scripten Sende ich Variablen Veränderungen von einem IPS zu einem Anderen. 