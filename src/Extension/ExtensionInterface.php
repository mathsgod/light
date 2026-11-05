<?php

declare(strict_types=1);

namespace Light\Framework\Extension;

use Light\Framework\App;

/** Registers an extension after the application has been constructed. */
interface ExtensionInterface
{
    public function register(App $app): void;
}
