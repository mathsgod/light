<?php

namespace Light\Controller;

use Light\Input\Translate as InputTranslate;
use Light\Model\Translate;
use TheCodingMachine\GraphQLite\Annotations\InjectUser;
use TheCodingMachine\GraphQLite\Annotations\Query;
use TheCodingMachine\GraphQLite\Annotations\Mutation;
use TheCodingMachine\GraphQLite\Annotations\Right;
use TheCodingMachine\GraphQLite\Annotations\Logged;


class TranslateController
{

    #[Mutation]
    #[Logged]
    public function addTranslate(InputTranslate $data, #[InjectUser] \Light\Model\User $user): int
    {
        $obj = Translate::Create();
        $obj->bind($data);
        $obj->save();
        return $obj->translate_id;
    }

    #[Mutation]
    #[Logged]
    public function deleteTranslate(int $id, #[InjectUser] \Light\Model\User $user): bool
    {
        if (!$obj = Translate::Get($id)) return false;
        if (!$obj->canDelete($user)) return false;
        $obj->delete();
        return true;
    }
}
