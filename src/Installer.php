<?php

namespace Light;

class Installer
{
    public static function postPackageInstall($event)
    {
        //copy Light.php to root

        $path = __DIR__ . "/Light.php";

        file_put_contents("Light", file_get_contents($path));
    }
}
