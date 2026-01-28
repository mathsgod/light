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
    #[Right('translate.update')]
    public function updateTranslate(string $name, string $value, string $language): bool
    {
        $q = Translate::Query(["language" => $language]);
        $q->where->expression("binary name = ?", [$name]);
        $t = $q->first();

        if (!$t) {
            $t = Translate::Create([
                "name" => $name,
                "value" => $value,
                "language" => $language
            ]);
        }
        $t->value = $value;
        $t->save();
        return true;
    }


    #[Logged]
    #[Query]
    /**
     * @return mixed[]
     */
    public function allTranslate(): array
    {
        $data = [];
        foreach (Translate::Query() as $translate) {
            $data[$translate->name] = $data[$translate->name] ?? [
                "name" => $translate->name,
            ];
            $data[$translate->name][$translate->language] = $translate->value;
        }

        //order by name
        ksort($data);

        return array_values($data);
    }

    #[Mutation]
    #[Logged]
    #[Right('translate.add')]
    public function addTranslate(InputTranslate $data, #[InjectUser] \Light\Model\User $user): int
    {
        foreach ($data->values as $value) {

            $q = Translate::Query(["language" => $value["language"]]);
            $q->where->expression("binary name = ?", [$data->name]);
            $t = $q->first();

            if (!$t) {
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
    #[Right('translate.delete')]
    public function deleteTranslate(string $name): bool
    {

        $q = Translate::Query();
        $q->where->expression("binary name = ?", [$name]);
        $q->delete();
        return true;
    }
}
