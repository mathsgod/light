<?php

namespace Light\Model;

use Laminas\Permissions\Rbac\Role as RbacRole;
use R\DB\Model;
use Symfony\Component\Yaml\Yaml;
use TheCodingMachine\GraphQLite\Annotations\Field;
use TheCodingMachine\GraphQLite\Annotations\InjectUser;
use TheCodingMachine\GraphQLite\Annotations\Logged;
use TheCodingMachine\GraphQLite\Annotations\MagicField;
use TheCodingMachine\GraphQLite\Annotations\Type;

#[Type]
class System
{

    #[Field]
    #[Logged]
    /**
     * @return string[]
     */
    public function getPasswordPolicy(): array
    {
        return [
            "required",
            "containUpper",
            "containLower",
            "containNumber",
            "containSpecial",
            "minLength:8"
        ];
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
