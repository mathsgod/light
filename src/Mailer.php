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

        if (Config::Value("mail_driver") == "gmail") {

            // get the eml file content
            try {
                if (!$this->preSend()) {
                    return false;
                }


                $data = [
                    "refresh_token" => Config::Value("mail_google_refresh_token"),
                    "header" => $this->MIMEHeader,
                    "body" => $this->MIMEBody,
                    "to" => $this->to
                ];

                $client = new \GuzzleHttp\Client([
                    "verify" => false
                ]);

                $resp = $client->post("https://raymond.hostlink.com.hk/light/send_gmail.php", [
                    "json" => $data
                ]);

                $data = json_decode($resp->getBody(), true);

                if ($data["status"] != "success") {
                    $this->setError($data["message"]);

                    if ($this->exceptions) {
                        throw new Exception($data["message"]);
                    }
                    return false;
                }
                return true;
            } catch (Exception $exc) {
                $this->mailHeader = '';
                $this->setError($exc->getMessage());
                if ($this->exceptions) {
                    throw $exc;
                }

                return false;
            }
        }

        return parent::send();
    }
}
