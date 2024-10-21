<?php


namespace Light\Type;

use Light\Model\Config;
use TheCodingMachine\GraphQLite\Annotations\Field;
use TheCodingMachine\GraphQLite\Annotations\Logged;
use TheCodingMachine\GraphQLite\Annotations\Type;

#[Type]
class Auth
{

    public function microsoftRegister(string $device_code)
    {
        $client_id = "fbe538f7-fe46-470c-9cc5-5f44e9abba84";
        $tenant_id = "common";
        $scopes = "user.read";
        $grant_type = "urn:ietf:params:oauth:grant-type:device_code";
        $token_url = "https://login.microsoftonline.com/" . $tenant_id . "/oauth2/v2.0/token";
        $client = new \GuzzleHttp\Client([
            "verify" => false
        ]);
        $response = $client->post($token_url, [
            'verify' => false,
            'form_params' => [
                'client_id' => $client_id,
                'grant_type' => $grant_type,
                'device_code' => $device_code
            ]
        ]);

        if ($response->getStatusCode() == 200) {
            $body = json_decode($response->getBody()->getContents());
            $access_token = $body->access_token;
            return $access_token;
        }
        return null;
    }

    #[Field()]
    #[Logged]
    /**
     * @return mixed
     */
    public function getMicrosoft()
    {

        $client_id = "fbe538f7-fe46-470c-9cc5-5f44e9abba84";
        //$client_id="159a9bfd-7b5c-4b6a-869c-b00a7471dea3";
        $tenant_id = "common";
        $scopes = "user.read";



        $deviceCodeRequestUrl = 'https://login.microsoftonline.com/' . $tenant_id . '/oauth2/v2.0/devicecode';
        $client = new \GuzzleHttp\Client([
            "verify" => false
        ]);
        $response = $client->post($deviceCodeRequestUrl, [
            'form_params' => [
                'client_id' => $client_id,
                'scope' => $scopes
            ]
        ]);

        return json_decode($response->getBody()->getContents());
    }



    #[Field()] function getGoogleClientId(): ?string
    {
        if (!\Composer\InstalledVersions::isInstalled("google/apiclient")) {
            return null;
        }

        if (!$google_client_id = Config::Value("authentication_google_client_id")) {
            return null;
        }

        return $google_client_id;
    }

    #[Field]
    public function getMicrosoftClientId(): ?string
    {
        if (!\Composer\InstalledVersions::isInstalled("microsoft/microsoft-graph")) {
            return null;
        }

        if (!$microsoft_client_id = Config::Value("authentication_microsoft_client_id")) {
            return null;
        }

        return $microsoft_client_id;
    }
}
