<?php

class ProcessmailComponent extends Object {

    //example for server, gmail: '{imap.gmail.com:993/imap/ssl/novalidate-cert}'
    var $server = false;
    var $login = false;
    var $password = false;
    var $deleteAfterRetr = TRUE;
    var $messages = array();
    private $connection;

    /**
     * function connect
     * @return boolean if successful, true else false
     */
    function connect() {
        if ($this->server && $this->login && $this->password):
            $this->connection = imap_open($this->server, $this->login, $this->password);
        else:
            throw new exception('Missing Connection Details');
        endif;
        return (float) $this->connection; // Returns boolean true or false
    }

    /**
     * function testConnection
     * @return mixed array if failed (error code and message), boolean true if successful 
     */
    function testConnection() {
        if (!$this->server)
            return array('errorCode' => -1001, 'msg' => 'No server defined');
        if (!$this->login)
            return array('errorCode' => -1002, 'msg' => 'No username defined');
        if (!$this->password)
            return array('errorCode' => -1003, 'msg' => 'No password defined');
        $temp = imap_open($this->server, $this->login, $this->password);
        if (!$temp):
            return array('errorCode' => -1010, 'msg' => imap_last_error());
        endif;
        imap_close($temp);
        return true;
    }

    /**
     * function count() 
     * @return int Number of Messages of objects IMAP/POP 
     */
    function count() {
        return imap_num_msg($this->connection);
    }

    /**
     * function getMessages()
     * 
     * @return array of messages and decoded (binary) attachments
     */
    function getMessages() {
        $this->connect();
        if ($this->connection):
            $count = $this->count();
            if($count>0):
            for ($msgno = 1; $msgno <= $count; $msgno++) {

                $headers = imap_headerinfo($this->connection, $msgno);
                $this->messages[$msgno]['fromAddress'] = $headers->fromaddress;
                $this->messages[$msgno]['senderAddress'] = $headers->senderaddress;
                $this->messages[$msgno]['toAddress'] = $headers->toaddress;
                $this->messages[$msgno]['replytoAddress'] = $headers->reply_toaddress;
                $this->messages[$msgno]['date'] = $headers->date;
                $this->messages[$msgno]['subject'] = $headers->subject;
                $this->messages[$msgno]['messageid'] = $headers->message_id;
                $this->messages[$msgno]['attachment'] = array();

                $struct = imap_fetchstructure($this->connection, $msgno);
                $parts = @$struct->parts;
                $i = 0;

                if (!$parts) { /* Simple message, only 1 piece */
                    $attachment = array(); /* No attachments */
                    $this->messages[$msgno]['messageBody'] = imap_body($this->connection, $msgno);
                } else { /* Complicated message, multiple parts */

                    $endwhile = false;

                    $stack = array();
                    $content = "";
                    $attachment = array();

                    while (!$endwhile) {
                        if (!$parts[$i]) {
                            if (count($stack) > 0) {
                                $parts = $stack[count($stack) - 1]["p"];
                                $i = $stack[count($stack) - 1]["i"] + 1;
                                array_pop($stack);
                            } else {
                                $endwhile = true;
                            }
                        }

                        if (!$endwhile) {
                            /* Create message part first (example '1.2.3') */
                            $partstring = "";
                            foreach ($stack as $s) {
                                $partstring .= ($s["i"] + 1) . ".";
                            }
                            $partstring .= ($i + 1);

                            if (strtoupper($parts[$i]->disposition) ==
                                    "ATTACHMENT") { /* Attachment */
                                $att = array("filename" =>
                                    $parts[$i]->parameters[0]->value,
                                    "filedata" =>
                                    imap_fetchbody($this->connection, $msgno, $partstring),
                                    "encoding" => $parts[$i]->encoding);
                                $this->messages[$msgno]['attachments'][] = $this->decode($att);
                            } elseif (strtoupper($parts[$i]->subtype) ==
                                    "PLAIN") { /* Message */
                                $this->messages[$msgno]['messageBody'] = imap_fetchbody($this->connection, $msgno, $partstring);
                            }
                        }

                        if (@$parts[$i]->parts) {
                            $stack[] = array("p" => $parts, "i" => $i);
                            $parts = $parts[$i]->parts;
                            $i = 0;
                        } else {
                            $i++;
                        }
                    } /* while */
                } /* complicated message */

                if ($this->deleteAfterRetr)
                    imap_delete($this->connection, $msgno);
            }
            endif;
            
            $this->disconnect();
        endif;
        return $this->messages;
    }

    function disconnect() {
        imap_close($this->connection);
    }

    /**
     * private function decode
     * @param array Attachment array from the main process function
     * @return array decoded attachment 
     */
    private function decode($att=false) {
        if ($att):
            $coding = $att['encoding'];
            if ($coding == 0) {
                $message = imap_8bit($att['filedata']);
            } elseif ($coding == 1) {
                $wiadomsoc = imap_8bit($att['filedata']);
            } elseif ($coding == 2) {
                $message = imap_binary($att['filedata']);
            } elseif ($coding == 3) {
                $message = imap_base64($att['filedata']);
            } elseif ($coding == 4) {
                $message = quoted_printable($att['filedata']);
            } elseif ($coding == 5) {
                $message = $att['filedata'];
            }
            $att['filedata'] = $message;

            return $att;
        endif;
        return array();
    }

}