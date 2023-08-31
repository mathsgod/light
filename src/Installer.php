<?php

namespace Light;

class Installer
{
    public static function postPackageInstall($event)
    {
        //copy Light.php to root

        $composer = $event->getComposer();

        $path = __DIR__ . "/Light.php";


        file_put_contents("light", file_get_contents($path));
    }
}
