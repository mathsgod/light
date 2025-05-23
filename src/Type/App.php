<?php

namespace Light\Type;

use Light\Type\Auth;
use Light\Rbac\Rbac;
use Light\App as LightApp;
use Light\Input\Table\Column;
use Light\Model\Config;
use Light\Model\CustomField;
use Light\Model\EventLog;
use Light\Model\MailLog;
use Light\Model\MyFavorite;
use Light\Model\Permission;
use Light\Model\Role;
use Light\Model\SystemValue;
use Light\Model\Translate;
use Light\Model\User;
use Light\Model\UserLog;
use TheCodingMachine\GraphQLite\Annotations\Autowire;
use TheCodingMachine\GraphQLite\Annotations\Field;
use TheCodingMachine\GraphQLite\Annotations\InjectUser;
use TheCodingMachine\GraphQLite\Annotations\Logged;
use TheCodingMachine\GraphQLite\Annotations\Right;
use TheCodingMachine\GraphQLite\Annotations\Type;

#[Type]
class App
{

    #[Field(outputType: "[mixed]")]
    public function getCustomFieldSchema(string $model)
    {

        $schemas = [];
        foreach (CustomField::Query(["model" => $model]) as $cf) {
            $schemas[] = $cf->getFormKitSchema();
        }

        return $schemas;



        return [
            [
                '$formkit' => 'l-input',
                'name' => 'name',
                'label' => 'Name',
            ]

        ];
    }


    #[Field(outputType: "[String]")]
    public function getCustomFieldModels()
    {
        $v = Config::Value("custom_field_models");
        if (!$v) return [];

        //explode by comma
        return explode(",", $v);
    }

    #[Field]
    public function getAuth(): Auth
    {
        return new Auth;
    }


    #[Field]
    #[Logged]
    public function getRole(#[Autowire] Rbac $rbac, string $name): ?Role
    {
        if (!$rbac->hasRole($name)) return null;
        return Role::LoadByRole($rbac->getRole($name));
    }


    #[Field]
    public function getVersion(): string
    {
        return \Composer\InstalledVersions::getVersion("mathsgod/light");
    }

    #[Field]
    public function isValidPassword(string $password): bool
    {
        return (new System)->isValidPassword($password);
    }


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
            if (!$menu["icon"]) {
                $menu["icon"] = "sym_o_circle";
            }

            if($menu["to"]=="/CustomField"){
                if(!Config::Value("custom_field_models")){
                    continue;
                }

            }


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
    function getMicrosoftTenantId(): ?string
    {
        return (new Auth)->getMicrosoftTenantId();
    }

    #[Field]
    function getMicrosoftClientId(): ?string
    {
        return (new Auth)->getMicrosoftClientId();
    }


    #[Field]
    function getFacebookAppId(): ?string
    {
        return (new Auth)->getFacebookAppId();
    }

    #[Field] function getGoogleClientId(): ?string
    {
        return (new Auth)->getGoogleClientId();
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

    #[Field]
    #[Logged]
    /**
     * @return mixed
     */
    function getCustomMenus(#[Autowire] LightApp $app): array
    {
        return $app->getCustomMenus();
    }

    #[Field]
    #[Logged]
    /**
     * @return string[]
     */
    function getDriveTypes(): array
    {
        $type = [];
        $type = ["local"];

        if (\Composer\InstalledVersions::isInstalled("league/flysystem-aws-s3-v3")) {
            $type[] = "s3";
        }

        if (\Composer\InstalledVersions::isInstalled("hostlink/hostlink-storage-adapter")) {
            $type[] = "hostlink";
        }

        if (\Composer\InstalledVersions::isInstalled("alphasnow/aliyun-oss-flysystem")) {
            $type[] = "aliyun-oss";
        }
        return $type;
    }

    #[Field]
    #[Logged]
    function getDrive(#[Autowire] LightApp $app, ?int $index = 0): \Light\Drive\Drive
    {
        $config = $app->getFSConfig();
        $fs = $config[$index] ?? $config[0];
        return new \Light\Drive\Drive($fs["name"], $app->getFS($index), $index, $fs["data"]);
    }

    #[Field]
    #[Logged]
    /**
     * @return \Light\Drive\Drive[]
     */
    function getDrives(#[Autowire] LightApp $app)
    {
        $config = $app->getFSConfig();

        $result = [];
        foreach ($config as $key => $fs) {
            $result[] = new \Light\Drive\Drive($fs["name"], $app->getFS($key), $key, $fs["data"]);
        }
        return $result;
    }

    #[Field]
    /**
     * @param ?mixed $filters
     * @return \Light\Model\MailLog[]
     */
    #[Right("maillog.list")]
    public function listMailLog($filters = [],  ?string $sort = ''): \Light\Db\Query
    {
        return MailLog::Query()->filters($filters)->sort($sort);
    }

    #[Field]
    /**
     * @param ?mixed $filters
     * @return \Light\Model\EventLog[]
     */
    #[Right("eventlog.list")]
    public function listEventLog($filters = [],  ?string $sort = ''): \Light\Db\Query
    {
        return EventLog::Query()->filters($filters)->sort($sort);
    }

    #[Field]
    #[Right('config.list')]
    #[Logged]
    /**
     * @return \Light\Model\Config[]
     * @param ?mixed $filters
     */
    public function listConfig($filters = [],  ?string $sort = '',): \Light\Db\Query
    {
        return Config::Query()->filters($filters)->sort($sort);
    }


    #[Field]
    #[Logged]
    /**
     * @return \Light\Model\User[]
     * @param ?mixed $filters
     */
    #[Right("user.list")]
    public function listUser(#[InjectUser] \Light\Model\User $user, $filters = [], ?string $sort = ""): \Light\Db\Query
    {
        //only administrators can list administrators
        $q = User::Query()->filters($filters)->sort($sort);
        if (!$user->is("Administrators")) {

            //filter out administrators
            $q->where("user_id NOT IN (SELECT user_id FROM UserRole WHERE role = 'Administrators')");
        }

        return $q;
    }



    #[Field]
    #[Logged]
    /**
     * @return \Light\Model\Role[]
     */
    #[Right("role.list")]
    public function getRoles(#[Autowire] Rbac $rbac, #[InjectUser] \Light\Model\User $user): array
    {
        $rs = [];
        foreach ($rbac->getRoles() as $role) {

            //only administrators can see administrators
            if ($role->getName() == "Administrators" && !$user->is("Administrators")) continue;

            //only administrators can see Everyone
            if ($role->getName() == "Everyone" && !$user->is("Administrators")) continue;

            $rs[] = Role::LoadByRole($role);
        }

        return $rs;
    }

    #[Field(outputType: "mixed")]
    #[Right("filesystem.list")]
    public function listFileSystem(): array
    {
        if (!$config = Config::Get(["name" => "fs"])) {
            return [];
        }
        return json_decode($config->value);
    }

    #[Field]
    #[Logged]
    /**
     * @return \Light\Model\SystemValue[]
     * @param ?mixed $filters
     */
    #[Right('systemvalue.list')]
    public function listSystemValue($filters = [],  ?string $sort = ''): \Light\Db\Query
    {
        return SystemValue::Query()->filters($filters)->sort($sort);
    }

    #[Field]
    #[Logged]
    /**
     * @return \Light\Model\UserLog[]
     * @param ?mixed $filters
     */
    #[Right("userlog.list")]
    public function listUserLog($filters = [],  ?string $sort = ''): \Light\Db\Query
    {
        return UserLog::Query()->filters($filters)->sort($sort);
    }

    #[Field]
    #[Right("permission.list")]
    /**
     * @param ?mixed $filters
     * @return \Light\Model\Permission[]
     */
    public function listPermission($filters = [],  ?string $sort = ''): \Light\Db\Query
    {
        return Permission::Query()->filters($filters)->sort($sort);
    }
}
