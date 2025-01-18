<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit3cb02a6db3f01373a68427c4334a1ac0
{
    public static $prefixLengthsPsr4 = array (
        'D' => 
        array (
            'DisembarkConnector\\' => 19,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'DisembarkConnector\\' => 
        array (
            0 => __DIR__ . '/../..' . '/app',
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
        'DisembarkConnector\\Backup' => __DIR__ . '/../..' . '/app/Backup.php',
        'DisembarkConnector\\Command' => __DIR__ . '/../..' . '/app/Command.php',
        'DisembarkConnector\\Run' => __DIR__ . '/../..' . '/app/Run.php',
        'DisembarkConnector\\Token' => __DIR__ . '/../..' . '/app/Token.php',
        'DisembarkConnector\\Updater' => __DIR__ . '/../..' . '/app/Updater.php',
        'DisembarkConnector\\User' => __DIR__ . '/../..' . '/app/User.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit3cb02a6db3f01373a68427c4334a1ac0::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit3cb02a6db3f01373a68427c4334a1ac0::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInit3cb02a6db3f01373a68427c4334a1ac0::$classMap;

        }, null, ClassLoader::class);
    }
}
