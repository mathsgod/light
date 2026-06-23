<?php

namespace Light;

use Exception;
use Light\Model\Config;
use Light\Model\MailLog;
use PHPMailer\PHPMailer\PHPMailer;

class Mailer extends PHPMailer
{

    public function __construct($exceptions = true)
    {
        parent::__construct($exceptions);
        $this->CharSet = "UTF-8";
    }

    public function send()
    {
       foreach ($this->to as $to) {
            $l = MailLog::Create([
                "subject"=> $this->Subject,
                "from"=> $this->From,
                "from_name"=> $this->FromName,
                "to"=> $to[0],
                "to_name"=> $to[1],
                "body"=> $this->Body,
                "altbody"=> $this->AltBody,
                "host"=> $this->Host
            ]);
            $l->save();
        }

        return parent::send();
    }
}
