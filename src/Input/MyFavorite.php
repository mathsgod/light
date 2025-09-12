<?php

namespace Light\Input;

use TheCodingMachine\GraphQLite\Annotations\Field;
use TheCodingMachine\GraphQLite\Annotations\Input;

#[Input(name: "CreateMyFavoriteInput", default: true)]
#[Input(name: "UpdateMyFavoriteInput", update: true)]
class MyFavorite
{
    #[Field]
    public ?string $path;

    #[Field]
    public ?string $label;

    #[Field]
    public ?string $icon;

    #[Field]
    public ?int $sequence;
}
