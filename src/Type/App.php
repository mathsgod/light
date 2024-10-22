<?php

namespace Light\Type;

use Light\Rbac\Rbac;
use Light\App as LightApp;
use Light\Model\Config;
use Light\Model\MyFavorite;
use Light\Model\Translate;
use Light\Model\User;
use TheCodingMachine\GraphQLite\Annotations\Autowire;
use TheCodingMachine\GraphQLite\Annotations\Field;
use TheCodingMachine\GraphQLite\Annotations\InjectUser;
use TheCodingMachine\GraphQLite\Annotations\Logged;
use TheCodingMachine\GraphQLite\Annotations\Right;
use TheCodingMachine\GraphQLite\Annotations\Type;

#[Type]
class App
{
    /*  #[Field]
    function testMS(): string
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

        return $response->getBody()->getContents();
    }
 */
    #[Field]
    public function hasFavorite(#[Autowire] LightApp $app): bool
    {
        return $app->hasFavorite();
    }


    #[Field]
    #[Logged]
    /**
     * @return \Light\Model\Translate[]
     */
    public function getTranslates(): array
    {
        return Translate::Query()->toArray();
    }


    #[Field]
    #[Logged]
    /**
     * @return \Light\Model\Translate[]
     */
    public function getI18nMessages(#[InjectUser] User $user): array
    {
        $language = $user->language ?? 'en';

        return Translate::Query(["language" => $language])->toArray();
    }


    #[Field(outputType: "mixed")]
    public function getLanguages()
    {
        return [
            ["name" => "English", "value" => "en"],
            ["name" => "中文", "value" => "zh-hk"]
        ];
    }

    #[Field]
    public function isViewAsMode(#[Autowire] \Light\Auth\Service $service): bool
    {
        return $service->isViewAsMode();
    }

    #[Field]
    public function hasBioAuth(): bool
    {
        //check if webauthn is enabled
        return \Composer\InstalledVersions::isInstalled("web-auth/webauthn-lib");
    }

    #[Field]
    public function isTwoFactorAuthentication(#[Autowire] LightApp $app): bool
    {
        return $app->isTwoFactorAuthentication();
    }

    #[Field]
    public function isDevMode(#[Autowire] LightApp $app): bool
    {
        return $app->isDevMode();
    }

    #[Field]
    #[Logged]
    /**
     * @return string[]
     */
    public function getPermissions(#[Autowire] LightApp $app): array
    {
        return $app->getPermissions();
    }


    #[Field(outputType: "mixed")]
    #[Logged]
    public function getMenus(#[InjectUser()] User $user, #[Autowire] LightApp $app)
    {
        return $this->filterMenus($app->getMenus(), $app->getRbac(), $user->getRoles());
    }

    private function filterMenus(array $menus, Rbac $rbac, array $roles)
    {
        $result = [];
        foreach ($menus as $menu) {
            if ($menu["children"]) {
                $menu["children"] = $this->filterMenus($menu["children"], $rbac, $roles);


                if (!$menu["to"] && empty($menu["children"])) {
                    continue;
                }
            }

            if ($permission = $menu["permission"]) {
                $canAccess = false;

                if (is_string($menu["permission"])) {
                    $permission = [$permission];
                }

                foreach ($permission as $p) {
                    foreach ($roles as $role) {
                        if ($rbac->hasRole($role) && $rbac->getRole($role)->can($p)) {
                            $canAccess = true;
                            break;
                        }
                    }
                }

                if ($canAccess) {
                    $result[] = $menu;
                }
            } else {
                $result[] = $menu;
            }
        }

        return $result;
    }

    #[Field]
    function getMicrosoftClientId(): ?string
    {
        //check user database, column facebook is exist
        if (!User::_table()->column("microsoft")) {
            return null;
        }
        return Config::Value("authentication_microsoft_client_id");
    }


    #[Field]
    function getFacebookAppId(): ?string
    {

        //check user database, column facebook is exist
        if (!User::_table()->column("facebook")) {
            return null;
        }

        return  Config::Value("authentication_facebook_app_id");
    }

    #[Field] function getGoogleClientId(): ?string
    {
        if (!\Composer\InstalledVersions::isInstalled("google/apiclient")) {
            return null;
        }

        //check user database, column facebook is exist
        if (!User::_table()->column("gmail")) {
            return null;
        }

        if (!$google_client_id = Config::Value("authentication_google_client_id")) {
            return null;
        }

        return $google_client_id;
    }



    #[Field]
    public function isForgetPasswordEnabled(): bool
    {
        return Config::Value("forget_password_enabled", true);
    }

    #[Field]
    public function getCompany(): string
    {
        return Config::Value("company", "HostLink");
    }

    #[Field]
    public function isPasswordBasedEnabled(): bool
    {
        return Config::Value("authentication_password_based", true);
    }

    #[Field]
    public function getCompanyLogo(): ?string
    {
        return Config::Value("company_logo");
    }

    #[Field]
    public function getCopyrightYear(): ?string
    {
        return Config::Value("copyright_year", date("Y"));
    }

    #[Field]
    public function getCopyrightName(): ?string
    {
        return Config::Value("copyright_name", "HostLink(HK)");
    }

    #[Field]
    public function isLogged(#[InjectUser] $user): bool
    {
        if ($user) return true;
        return false;
    }


    #[Field]
    #[Logged]
    #[Right("config")]
    /**
     * @return \Light\Model\Config[]
     */
    public function getConfig(): array
    {
        return Config::Query()->toArray();
    }
}
