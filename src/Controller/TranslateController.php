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
    #[Right('translate.add')]
    public function addTranslate(InputTranslate $data, #[InjectUser] \Light\Model\User $user): int
    {
        foreach ($data->values as $value) {

            if (!$t = Translate::Get(["name" => $data->name, "language" => $value["language"]])) {
                $t = Translate::Create([
                    "name" => $data->name,
                    "value" => $value["value"],
                    "language" => $value["language"]
                ]);
            }
            $t->value = $value["value"];

            $t->save();
        }

        /*     $obj = Translate::Create();
        $obj->bind($data);
        $obj->save();
        return $obj->translate_id; */
        return 1;
    }

    #[Mutation]
    #[Logged]
    #[Right('translate.edit')]
    public function deleteTranslate(int $id, #[InjectUser] \Light\Model\User $user): bool
    {
        if (!$obj = Translate::Get($id)) return false;
        if (!$obj->canDelete($user)) return false;
        $obj->delete();
        return true;
    }
}
