<?php

namespace Light\Controller;

use Light\App as LightApp;
use Light\Model\Config;
use Light\Model\User;
use Light\Type\App;
use TheCodingMachine\GraphQLite\Annotations\Autowire;
use TheCodingMachine\GraphQLite\Annotations\InjectUser;
use TheCodingMachine\GraphQLite\Annotations\Query;
use TheCodingMachine\GraphQLite\Annotations\Mutation;
use TheCodingMachine\GraphQLite\Annotations\Right;
use TheCodingMachine\GraphQLite\Annotations\Logged;
use Psr\Http\Message\UploadedFileInterface;

class AppController
{


    #[Query]
    public function test(#[InjectUser] User $user, #[Autowire] LightApp $app): string
    {

        return $user->is("Administrators");



        //return $user->is("Administrators");
    }


    #[Query]
    public function getApp(): App
    {
        return new App();
    }

    #[Mutation]
    #[Logged]
    #[Right('config.update')]
    /**
     * @param mixed $data
     */
    function updateAppConfigs(array $data, #[Autowire] LightApp $app): bool
    {
        foreach ($data as $d) {
            if (!$config = Config::Get(["name" => $d['name']])) {
                $config = Config::Create([
                    "name" => $d['name']
                ]);
            }
            $config->value = $d['value'];
            $config->save();
        }

        //flush cache
        $app->getCache()->clear();

        return true;
    }

    #[Mutation]
    #[Logged]
    #[Right('config.update')]
    function updateAppConfig(string $name, string $value): bool
    {
        if (!$config = Config::Get(["name" => $name])) {
            $config = Config::Create([
                "name" => $name
            ]);
        }
        $config->value = $value;
        $config->save();
        return true;
    }

    #[Mutation]
    #[Logged]
    #[Right('menu.update')]
    /**
     * @param mixed $data
     */
    function updateAppMenus(array $data): bool
    {
        if (!$menus = Config::Get(["name" => "menus"])) {
            $menus = Config::Create([
                "name" => "menus"
            ]);
        }
        $menus->value = json_encode($data);
        $menus->save();

        return true;
    }

    #[Query]
    #[Logged]
    /**
     * @return mixed
     */
    function getAppMenus(#[Autowire] LightApp $app): array
    {
        return $app->getCustomMenus();
    }


    #[Mutation]
    #[Logged]
    /**
     * @param mixed $value
     */
    public function updateMyStyle(string $name, #[InjectUser] User $user, $value): bool
    {
        $user->updateStyle($name, $value);
        return true;
    }
    #[Mutation]
    #[Logged]
    /**
     * @param mixed $value
     */
    public function updateMyStyles(#[InjectUser] User $user, array $value): bool
    {
        foreach ($value as $key => $val) {
            $user->updateStyle($key, $val);
        }
        return true;
    }



    #[Mutation]
    #[Logged]
    public function updateMyLanguage(string $name, #[InjectUser] User $user): bool
    {
        $user->language = $name;
        $user->save();
        return true;
    }
}
