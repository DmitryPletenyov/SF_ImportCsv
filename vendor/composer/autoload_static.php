<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInite8aea1c04f4953e6fd6027b619ba486f
{
    public static $prefixLengthsPsr4 = array (
        'A' => 
        array (
            'Asan\\PHPExcel\\' => 14,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Asan\\PHPExcel\\' => 
        array (
            0 => __DIR__ . '/..' . '/asan/phpexcel/src',
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInite8aea1c04f4953e6fd6027b619ba486f::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInite8aea1c04f4953e6fd6027b619ba486f::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInite8aea1c04f4953e6fd6027b619ba486f::$classMap;

        }, null, ClassLoader::class);
    }
}