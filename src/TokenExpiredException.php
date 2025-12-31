<?php

namespace Light;

use GraphQL\Error\ClientAware;
use GraphQL\Error\ProvidesExtensions;
use Exception;

class TokenExpiredException extends Exception implements ClientAware, ProvidesExtensions
{
    /**
     * 讓這個錯誤在生產環境也能被前端看到
     */
    public function isClientSafe(): bool
    {
        return true;
    }

    /**
     * 這裡就是定義 extensions 內容的地方
     */
    public function getExtensions(): array
    {
        return [
            'code' => 'TOKEN_EXPIRED',
            'category' => 'auth',
        ];
    }
}
