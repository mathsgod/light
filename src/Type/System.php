<?php

namespace Light\Type;

use Light\App;
use Light\Database\Schema;
use Light\Model\Config;
use Light\Util;
use TheCodingMachine\GraphQLite\Annotations\Autowire;
use TheCodingMachine\GraphQLite\Annotations\FailWith;
use TheCodingMachine\GraphQLite\Annotations\Field;
use TheCodingMachine\GraphQLite\Annotations\InjectUser;
use TheCodingMachine\GraphQLite\Annotations\Logged;
use TheCodingMachine\GraphQLite\Annotations\Right;
use TheCodingMachine\GraphQLite\Annotations\Type;

#[Type]
class System
{

    #[Field]
    #[Logged]
    public function getArch(): string
    {
        $system = new \Utopia\System\System();
        return $system->getArch();
    }

    #[Field(name: "os")]
    #[Logged]
    public function getOS(): string
    {
        $system = new \Utopia\System\System();
        return $system->getOS();
    }

    #[Field(name: "CPUCores")]
    #[Logged]
    public function getCPUCores(): ?int
    {
        try {
            $system = new \Utopia\System\System();
            return $system->getCPUCores();
        } catch (\Exception $e) {
            return null;
        }
    }

    #[Field]
    #[Logged]
    public function getHostname(): string
    {
        $system = new \Utopia\System\System();
        return $system->getHostname();
    }

    #[Field]
    #[Logged]
    public function getMemoryTotal(): ?int
    {
        try {
            $system = new \Utopia\System\System();
            return $system->getMemoryTotal();
        } catch (\Exception $e) {
            return null;
        }
    }

    #[Field]
    #[Logged]
    public function getMemoryFree(): ?int
    {
        try {
            $system = new \Utopia\System\System();
            return $system->getMemoryFree();
        } catch (\Exception $e) {
            return null;
        }
    }

    #[Field]
    #[Logged]
    public function getMemoryAvailable(): ?int
    {
        try {
            $system = new \Utopia\System\System();
            return $system->getMemoryAvailable();
        } catch (\Exception $e) {
            return null;
        }
    }

    #[Field]
    public function time(): string
    {
        return date("Y-m-d H:i:s");
    }

    #[Field]
    public function isDevMode(#[Autowire] App $app): bool
    {
        return $app->isDevMode();
    }

    #[Field]
    public function getMaxUploadSize(): int
    {
        $max_size = -1;
        if ($max_size < 0) {
            // Start with post_max_size.
            $post_max_size = \Light\Util::ParseSize(ini_get('post_max_size'));
            if ($post_max_size > 0) {
                $max_size = $post_max_size;
            }

            // If upload_max_size is less, then reduce. Except if upload_max_size is
            // zero, which indicates no limit.
            $upload_max = \Light\Util::ParseSize(ini_get('upload_max_filesize'));
            if ($upload_max > 0 && $upload_max < $max_size) {
                $max_size = $upload_max;
            }
        }
        return $max_size;
    }



    #[Field]
    #[Right("system.storage")]

    public function getDiskUsageSpace(): string
    {
        return Util::Size(disk_total_space(getcwd()) - disk_free_space(getcwd()));
    }

    #[Field]
    #[Right("system.storage")]
    #[Logged]
    #[FailWith(["value" => null])]
    public function getStorage(): ?Storage
    {
        return new Storage;
    }

    #[Field]
    #[Right("system.storage")]
    /**
     * @deprecated Use storage instead
     */
    public function getDiskFreeSpacePercent(): float
    {
        return disk_free_space(getcwd()) / disk_total_space(getcwd());
    }

    #[Field]
    #[Right("system.storage")]
    /**
     * @deprecated Use storage instead
     */
    public function getDiskFreeSpace(): string
    {
        return Util::Size(disk_free_space(getcwd()));
    }

    #[Field]
    #[Right("system.storage")]
    /**
     * @deprecated Use storage instead
     */
    public function getDiskTotalSpace(): string
    {
        return Util::Size(disk_total_space(getcwd()));
    }





    #[Field]
    #[Right("system.database")]
    public function getDatabase(): Schema
    {
        return new Schema;
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
        //$phpinfo = str_replace("module_Zend Optimizer", "module_Zend_Optimizer", preg_replace('%^.*<body>(.*)</body>.*$%ms', '$1', $phpinfo));
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
            $policy[] = "length:" . Config::Value("password_min_length");
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

            if ($rule === "contains_uppercase") {
                if (!preg_match("/[A-Z]/", $password)) return false;
            }

            if ($rule === "contains_lowercase") {
                if (!preg_match("/[a-z]/", $password)) return false;
            }

            if ($rule === "contains_numeric") {
                if (!preg_match("/[0-9]/", $password)) return false;
            }

            if ($rule === "contains_symbol") {
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
        return null;
    }

    #[Field]
    public function isLogged(#[InjectUser] $user): bool
    {
        if ($user) return true;
        return false;
    }
}
