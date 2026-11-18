<?php

namespace Light\Framework\Type;

use Light\Framework\App;
use Light\Framework\Database\Schema;
use Light\Framework\Model\Config;
use Light\Framework\Util;
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
            $post_max_size = \Light\Framework\Util::ParseSize(ini_get('post_max_size'));
            if ($post_max_size > 0) {
                $max_size = $post_max_size;
            }

            // If upload_max_size is less, then reduce. Except if upload_max_size is
            // zero, which indicates no limit.
            $upload_max = \Light\Framework\Util::ParseSize(ini_get('upload_max_filesize'));
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
        return implode('|', $this->passwordPolicy()->rules());
    }

    public function isValidPassword(#[\SensitiveParameter] string $password): bool
    {
        return $this->passwordPolicy()->accepts($password);
    }

    private function passwordPolicy(): \Light\Auth\PasswordPolicy
    {
        return new \Light\Auth\PasswordPolicy(
            minLength: (int) Config::Value('password_min_length'),
            requireUppercase: (bool) Config::Value('password_contains_uppercase'),
            requireLowercase: (bool) Config::Value('password_contains_lowercase'),
            requireNumeric: (bool) Config::Value('password_contains_numeric'),
            requireSymbol: (bool) Config::Value('password_contains_symbol'),
        );
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
