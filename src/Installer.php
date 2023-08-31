<?php

namespace Light;

class Installer
{
    public static function postPackageInstall($event)
    {
        //copy Light.php to root

        $path = __DIR__ . "/Light.php";
        $dest = getcwd() . "/light";
        copy($path, $dest);
    }
}
