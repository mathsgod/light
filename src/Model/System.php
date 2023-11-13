<?php

namespace Light\Model;

use Light\Type\Database;
use Light\Util;
use TheCodingMachine\GraphQLite\Annotations\Field;
use TheCodingMachine\GraphQLite\Annotations\InjectUser;
use TheCodingMachine\GraphQLite\Annotations\Logged;
use TheCodingMachine\GraphQLite\Annotations\Right;
use TheCodingMachine\GraphQLite\Annotations\Type;

#[Type]
class System
{
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
    /**
     * @return string[]
     */
    public function getPasswordPolicy(): array
    {
        $policy = ["required"];
        if ($_ENV["PASSWORD_POLICY_CONTAIN_UPPER"]) {
            $policy[] = "containUpper";
        }
        if ($_ENV["PASSWORD_POLICY_CONTAIN_LOWER"]) {
            $policy[] = "containLower";
        }
        if ($_ENV["PASSWORD_POLICY_CONTAIN_NUMBER"]) {
            $policy[] = "containNumber";
        }
        if ($_ENV["PASSWORD_POLICY_CONTAIN_SPECIAL"]) {
            $policy[] = "containSpecial";
        }
        if ($_ENV["PASSWORD_POLICY_MIN_LENGTH"]) {
            $policy[] = "minLength:" . $_ENV["PASSWORD_POLICY_MIN_LENGTH"];
        }
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
        foreach ($this->getPasswordPolicy() as $rule) {
            if ($rule === "required") {
                if (empty($password)) return false;
            }
            if ($rule === "containUpper") {
                if (!preg_match("/[A-Z]/", $password)) return false;
            }
            if ($rule === "containLower") {
                if (!preg_match("/[a-z]/", $password)) return false;
            }

            if ($rule === "containNumber") {
                if (!preg_match("/[0-9]/", $password)) return false;
            }

            if ($rule === "containSpecial") {
                if (!preg_match("/[!@#$%^&*()\-_=+{};:,<.>]/", $password)) return false;
            }

            if (strpos($rule, "minLength:") === 0) {
                $minLength = (int) str_replace("minLength:", "", $rule);
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
