<?php
class ZKLibrary {
    public $ip;
    public $port;
    public $socket = null;
    public $session_id = 0;

    const CMD_CONNECT = 1000;
    const CMD_DISCONNECT = 1001;
    const CMD_ENABLEDEVICE = 74;
    const CMD_DISABLEDEVICE = 76;
    const CMD_ATTLOG_RRQ = 13;
    const CMD_ACK_OK = 2000;
    const CMD_PREPARE_DATA = 1500;

    public function __construct($ip = '192.168.1.171', $port = 4370) {
        $this->ip = $ip;
        $this->port = $port;
    }

    private function createHeader($command, $chksum, $session_id, $reply_id, $command_string) {
        $buf = pack('v', $command) . pack('v', $chksum) . pack('v', $session_id) . pack('v', $reply_id) . $command_string;
        $chksum = 0;
        for ($i = 0; $i < strlen($buf); $i += 2) {
            $chksum += ($i == strlen($buf) - 1) ? ord($buf[$i]) : ord($buf[$i]) + (ord($buf[$i + 1]) << 8);
        }
        while ($chksum > 0xFFFF) $chksum = ($chksum & 0xFFFF) + ($chksum >> 16);
        $chksum = ~$chksum & 0xFFFF;
        return pack('v', $command) . pack('v', $chksum) . pack('v', $session_id) . pack('v', $reply_id) . $command_string;
    }

    public function connect() {
        // Usamos TCP, el estándar para transferencia de datos en estos modelos
        $this->socket = @fsockopen("tcp://" . $this->ip, $this->port, $errno, $errstr, 10);
        if (!$this->socket) return false;

        fwrite($this->socket, $this->createHeader(self::CMD_CONNECT, 0, 0, 0, ''));
        $res = fread($this->socket, 1024);
        
        if (strlen($res) >= 8) {
            $hdr = unpack('vcmd/vchksum/vsession_id/vreply_id', substr($res, 0, 8));
            if ($hdr['cmd'] == self::CMD_ACK_OK) {
                $this->session_id = $hdr['session_id'];
                return true;
            }
        }
        return false;
    }

    public function disconnect() {
        if ($this->socket) {
            fwrite($this->socket, $this->createHeader(self::CMD_DISCONNECT, 0, $this->session_id, 0, ''));
            fclose($this->socket);
        }
    }

    private function disableDevice() {
        fwrite($this->socket, $this->createHeader(self::CMD_DISABLEDEVICE, 0, $this->session_id, 0, ''));
        fread($this->socket, 1024);
    }

    private function enableDevice() {
        fwrite($this->socket, $this->createHeader(self::CMD_ENABLEDEVICE, 0, $this->session_id, 0, ''));
        fread($this->socket, 1024);
    }

    private function decodeTime($t) {
        $t = (int)$t;
        $second = $t % 60; $t = (int)($t / 60);
        $minute = $t % 60; $t = (int)($t / 60);
        $hour = $t % 24;   $t = (int)($t / 24);
        $day = $t % 31 + 1;  $t = (int)($t / 31);
        $month = $t % 12 + 1; $t = (int)($t / 12);
        $year = floor($t + 2000);
        return sprintf("%04d-%02d-%02d %02d:%02d:%02d", $year, $month, $day, $hour, $minute, $second);
    }

    public function getAttendance() {
        // PASO CLAVE: Congelar el reloj antes de pedir datos
        $this->disableDevice();
        
        fwrite($this->socket, $this->createHeader(self::CMD_ATTLOG_RRQ, 0, $this->session_id, 0, ''));
        
        $attendances = [];
        $bytes = '';
        
        while (true) {
            $res = fread($this->socket, 1024);
            if (empty($res)) break; 
            
            $hdr = unpack('vcmd/vchk/vsession/vreply', substr($res, 0, 8));
            if ($hdr['cmd'] == self::CMD_PREPARE_DATA) continue; 
            
            if ($hdr['cmd'] == self::CMD_ACK_OK) {
                if (strlen($res) > 8) $bytes .= substr($res, 8); 
                break;
            }
            if (strlen($res) > 8) $bytes .= substr($res, 8);
        }

        // PASO CLAVE: Descongelar el reloj al terminar
        $this->enableDevice();

        if (strlen($bytes) > 0) {
            $data = $bytes;
            while (strlen($data) >= 40) {
                $registro_raw = substr($data, 0, 40);
                $data = substr($data, 40);
                
                $uid = unpack('v', substr($registro_raw, 0, 2))[1];
                $id = (int)trim(str_replace("\0", '', substr($registro_raw, 2, 24)));
                $state = ord($registro_raw[26]);
                $timestamp = unpack('V', substr($registro_raw, 28, 4))[1];
                $fecha = $this->decodeTime($timestamp);
                
                if ($id > 0) {
                    $attendances[] = [$uid, $id, $state, $fecha];
                }
            }
        }
        return $attendances;
    }
}
?>