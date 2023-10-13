<?php

namespace Light\Type;

use TheCodingMachine\GraphQLite\Annotations\Field;
use TheCodingMachine\GraphQLite\Annotations\Type;

#[Type]
class WebAuthn
{

    #[Field]
    public string $uuid;

    #[Field]
    public string $ip;

    #[Field]
    public string $user_agent;

    #[Field]
    public string $timestamp;

    public function __construct(array $data)
    {
        $this->ip = $data["ip"];
        $this->user_agent = $data["user-agent"];
        $this->uuid = $data["uuid"];
        $this->timestamp = $data["timestamp"];
    }

    #[Field]
    public function getCreatedTime(): string
    {
        return date("Y-m-d H:i:s", $this->timestamp);
    }



    /*    #[Field]
    public function getIp(): string
    {
        return $this->ip;
    } */
}
