<?php

namespace Light\Input;

use TheCodingMachine\GraphQLite\Annotations\Field;
use TheCodingMachine\GraphQLite\Annotations\Input;
use TheCodingMachine\GraphQLite\Undefined;

#[Input(name: "CreateMyFavoriteInput", default: true)]
#[Input(name: "UpdateMyFavoriteInput")]
class MyFavorite
{
    #[Field]
    public string|null|Undefined $path = Undefined::VALUE;

    #[Field]
    public string|null|Undefined $label = Undefined::VALUE;

    #[Field]
    public string|null|Undefined $icon = Undefined::VALUE;

    #[Field]
    public int|null|Undefined $sequence = Undefined::VALUE;
}
