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
use TheCodingMachine\GraphQLite\Annotations\Type;

#[Type]
class App
{

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

    #[Field] function getGoogleClientId(): ?string
    {
        if (!\Composer\InstalledVersions::isInstalled("google/apiclient")) {
            return null;
        }

        if (!$google_client_id = $_ENV["GOOGLE_CLIENT_ID"]) {
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
        if ($c = Config::Get(["name" => "company"])) {
            return $c->value;
        }
        return "HostLink";
    }

    #[Field]
    public function getCompanyLogo(): ?string
    {
        if ($c = Config::Get(["name" => "company_logo"])) {
            return $c->value;
        }
    }

    #[Field]
    public function getCopyrightYear(): string
    {
        return Config::Value("copyright_year", date("Y"));
    }

    #[Field]
    public function getCopyrightName(): string
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
    /**
     * @return \Light\Model\Config[]
     */
    public function getConfig(): array
    {

        return Config::Query()->toArray();
    }
}
