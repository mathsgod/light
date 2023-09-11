<?php

namespace Light\Type;

use Light\Model\User;
use Symfony\Component\Yaml\Yaml;
use TheCodingMachine\GraphQLite\Annotations\Field;
use TheCodingMachine\GraphQLite\Annotations\InjectUser;
use TheCodingMachine\GraphQLite\Annotations\Type;

#[Type]
class App
{

    #[Field]
    /**
     * @return mixed
     */
    public function getMenus(#[InjectUser()] User $user)
    {
        $menu = Yaml::parseFile(dirname(__DIR__, 2) . '/menus.yml');
        return $menu;
    }
}
