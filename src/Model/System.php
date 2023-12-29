<?php

namespace Light\Model;

use Light\App;
use Light\Type\Database;
use Light\Util;
use TheCodingMachine\GraphQLite\Annotations\Autowire;
use TheCodingMachine\GraphQLite\Annotations\Field;
use TheCodingMachine\GraphQLite\Annotations\InjectUser;
use TheCodingMachine\GraphQLite\Annotations\Logged;
use TheCodingMachine\GraphQLite\Annotations\Right;
use TheCodingMachine\GraphQLite\Annotations\Type;

#[Type]
class System
{
    #[Field]
    public function isDevMode(#[Autowire] App $app): bool
    {
        return $app->isDevMode();
    }

    #[Field]
    #[Right("system.storage")]
    public function getDiskUsageSpace(): string
    {
        return Util::Size(disk_total_space(getcwd()) - disk_free_space(getcwd()));
    }


    #[Field]
    #[Right("system.storage")]
    public function getDiskFreeSpacePercent(): float
    {
        return disk_free_space(getcwd()) / disk_total_space(getcwd());
    }

    #[Field]
    #[Right("system.storage")]
    public function getDiskFreeSpace(): string
    {
        return Util::Size(disk_free_space(getcwd()));
    }

    #[Field]
    #[Right("system.storage")]
    public function getDiskTotalSpace(): string
    {
        return Util::Size(disk_total_space(getcwd()));
    }

    #[Field]
    #[Right("system.database")]
    public function getDatabase(): Database
    {
        return new Database;
    }

    #[Field]
    /**
     * @return mixed
     */
    #[Right("system.package")]
    public function getPackage(): array
    {
        $data = [];
        foreach (\Composer\InstalledVersions::getInstalledPackages() as $package) {
            $data[] = [
                "name" => $package,
                "version" => \Composer\InstalledVersions::getVersion($package)
            ];
        }
        return $data;
    }

    #[Field]
    /**
     * @return mixed
     */
    #[Right("system.server")]
    public function getServer(): array
    {
        //map $_SERVER to name value
        $server = [];
        foreach ($_SERVER as $key => $value) {
            $server[] = ["name" => $key, "value" => $value];
        }
        return $server;
    }

    #[Field]
    #[Right("system.phpinfo")]
    public function getPhpInfo(): string
    {
        ob_start();
        phpinfo();
        $phpinfo = ob_get_contents();
        ob_end_clean();
        $phpinfo = str_replace("module_Zend Optimizer", "module_Zend_Optimizer", preg_replace('%^.*<body>(.*)</body>.*$%ms', '$1', $phpinfo));
        return $phpinfo;
    }



    #[Field]
    #[Logged]
    public function getPasswordPolicy(): string
    {
        $policy = ["required"];
        if (Config::Value("password_contains_uppercase")) {
            $policy[] = "contains_uppercase";
        }
        if (Config::Value("password_contains_lowercase")) {
            $policy[] = "contains_lowercase";
        }
        if (Config::Value("password_contains_numeric")) {
            $policy[] = "contains_numeric";
        }
        if (Config::Value("password_contains_symbol")) {
            $policy[] = "contains_symbol";
        }
        if (Config::Value("password_min_length")) {
            $policy[] = "length:" . $_ENV["PASSWORD_POLICY_MIN_LENGTH"];
        }

        //join to string
        $policy = implode("|", $policy);
        return $policy;
    }


    /**
     * Check if the given password is valid.
     *
     * @param string $password The password to validate.
     * @return bool Returns true if the password is valid, false otherwise.
     */
    public function isValidPassword(string $password)
    {

        $policy = $this->getPasswordPolicy();
        //explode to array
        $policy = explode("|", $policy);


        foreach ($policy as $rule) {
            if ($rule === "required") {
                if (empty($password)) return false;
            }

            if ($rule === "contain_upper") {
                if (!preg_match("/[A-Z]/", $password)) return false;
            }

            if ($rule === "contain_lower") {
                if (!preg_match("/[a-z]/", $password)) return false;
            }

            if ($rule === "contain_numeric") {
                if (!preg_match("/[0-9]/", $password)) return false;
            }

            if ($rule === "contain_symbol") {
                if (!preg_match("/[!@#$%^&*()\-_=+{};:,<.>]/", $password)) return false;
            }

            if (strpos($rule, "length:") !== false) {
                $minLength = (int) str_replace("length:", "", $rule);
                if (strlen($password) < $minLength) return false;
            }
        }

        return true;
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
    public function isLogged(#[InjectUser] $user): bool
    {
        if ($user) return true;
        return false;
    }
}
