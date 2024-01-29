<?php

namespace Light\Controller;

use Light\App as LightApp;
use Light\Model\Config;
use Light\Model\MyFavorite;
use Light\Model\User;
use Light\Type\App;
use TheCodingMachine\GraphQLite\Annotations\Autowire;
use TheCodingMachine\GraphQLite\Annotations\InjectUser;
use TheCodingMachine\GraphQLite\Annotations\Query;
use TheCodingMachine\GraphQLite\Annotations\Mutation;
use TheCodingMachine\GraphQLite\Annotations\Right;
use TheCodingMachine\GraphQLite\Annotations\Logged;

class MyFavoriteController
{


    #[Mutation]
    #[Logged]
    public function removeMyFavorite(
        #[InjectUser] User $user,
        string $path,
    ): bool {
        $myfav = MyFavorite::Query([
            "user_id" => $user->user_id,
            "path" => $path,
        ])->first();
        if ($myfav) {
            $myfav->delete();
        }
        return true;
    }


    #[Mutation]
    #[Logged]
    public function addMyFavorite(
        #[InjectUser] User $user,
        string $label,
        string $path,
    ): bool {
        $user->addMyFavorite($label, $path);
        return true;
    }
}
