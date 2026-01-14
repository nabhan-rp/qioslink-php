<?php
// CLASS SimpleSMTP - V2 (Robust Connection)
// Menggunakan stream_socket_client untuk support bypass SSL Verification (Self-Signed Certs)

class SimpleSMTP {
    private $host;
    private $port;
    private $user;
    private $pass;
    private $secure; // 'ssl', 'tls', or 'none'
    private $conn;
    private $debug = false;

    public function __construct($host, $port, $user, $pass, $secure = 'tls') {
        $this->host = $host;
        $this->port = $port;
        $this->user = $user;
        $this->pass = $pass;
        $this->secure = $secure;
    }

    private function log($msg) {
        if ($this->debug) error_log("SMTP: $msg");
    }

    private function getResponse() {
        $response = "";
        while ($str = fgets($this->conn, 515)) {
            $response .= $str;
            if (substr($str, 3, 1) == " ") { break; }
        }
        $this->log("Server: $response");
        return $response;
    }

    private function sendCmd($cmd, $expectedCode = null) {
        $this->log("Client: $cmd");
        fwrite($this->conn, $cmd . "\r\n");
        
        $response = $this->getResponse();
        
        if ($expectedCode && substr($response, 0, 3) != $expectedCode) {
            throw new Exception("SMTP Error: Expected $expectedCode, got $response");
        }
        return $response;
    }

    public function send($to, $subject, $bodyHTML, $fromEmail, $fromName) {
        try {
            // 1. Setup Protocol Prefix
            $protocol = "tcp://";
            if ($this->secure === 'ssl') {
                $protocol = "ssl://";
            }

            // 2. Setup Context (THE FIX: Allow Self-Signed Certs)
            // Penting untuk hosting dimana Mail Server & Web Server ada di jaringan lokal yang sama
            $context = stream_context_create([
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ]
            ]);

            // 3. Connect using stream_socket_client
            $socketUrl = $protocol . $this->host . ":" . $this->port;
            $this->conn = stream_socket_client(
                $socketUrl, 
                $errno, 
                $errstr, 
                30, 
                STREAM_CLIENT_CONNECT, 
                $context
            );
            
            if (!$this->conn) {
                throw new Exception("Could not connect to SMTP host ($socketUrl): $errstr ($errno)");
            }
            
            // 4. Handshake
            $this->getResponse(); // Read banner
            $this->sendCmd("EHLO " . $_SERVER['HTTP_HOST']);

            // 5. STARTTLS Logic (Only if using TLS mode on port 587/25)
            if ($this->secure === 'tls') {
                $this->sendCmd("STARTTLS", "220");
                // Upgrade socket to secure
                stream_socket_enable_crypto($this->conn, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                $this->sendCmd("EHLO " . $_SERVER['HTTP_HOST']);
            }

            // 6. AUTH LOGIN
            $this->sendCmd("AUTH LOGIN", "334");
            $this->sendCmd(base64_encode($this->user), "334");
            $this->sendCmd(base64_encode($this->pass), "235");

            // 7. Send Headers
            $this->sendCmd("MAIL FROM: <$fromEmail>", "250");
            $this->sendCmd("RCPT TO: <$to>", "250");
            $this->sendCmd("DATA", "354");

            // 8. Send Body
            $headers  = "MIME-Version: 1.0\r\n";
            $headers .= "Content-type: text/html; charset=utf-8\r\n";
            $headers .= "From: $fromName <$fromEmail>\r\n";
            $headers .= "To: <$to>\r\n";
            $headers .= "Subject: $subject\r\n";
            $headers .= "Date: " . date("r") . "\r\n";

            fwrite($this->conn, $headers . "\r\n" . $bodyHTML . "\r\n.\r\n");
            
            $response = $this->getResponse();
            if (substr($response, 0, 3) != "250") {
                 throw new Exception("Failed to send data: $response");
            }

            // 9. Quit
            $this->sendCmd("QUIT");
            fclose($this->conn);
            return true;

        } catch (Exception $e) {
            if ($this->conn && is_resource($this->conn)) fclose($this->conn);
            throw $e;
        }
    }
}
?>