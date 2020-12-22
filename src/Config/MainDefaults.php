<?php declare(strict_types=1);

namespace BrunoNatali\BestPrice\Config;

class MainDefaults implements MainDefaultsInterface
{
    /**
     * Just merge default configs
     * 
     * @return array merged configurations from interface
    */
    public static function getDefaults(): array
    {
        return \array_merge(
            self::CONFIG_APP,
            self::CONFIG_HTTP_ROWSER,
            self::CONFIG_HTTP_SERVER_BLACKLIST
        );
    }
    
    /**
     * Create / save configuration file with defaults
     * 
     * @param bool $verbose - Output more detailed information 
     * @return bool
    */
    public static function applyDefaults(bool $verbose = false, $configFilePath = __DIR__ . '/main.json'): bool
    {
        if ($verbose)
            echo 'Applying default configuration to config file: ';

        $result = \BrunoNatali\Tools\File\JsonFile::saveArray(
            $configFilePath, 
            self::getDefaults(), 
            true,
            JSON_PRETTY_PRINT // 4 Better human visualization or hand edition 
        );

        if ($verbose)
            echo ($result ? 'OK' : 'ERROR') . PHP_EOL; 

        return $result;
    }
    
    /**
     * Create / update existent configuration file with new version configs
     * 
     * This function was designed to be used after an app update, to place new configs 
     *  to exisent file leavving current config untouched
     * 
     * @param bool $verbose - Output more detailed information 
     * @return bool
    */
    public static function updateDefaults(bool $verbose = false, $configFilePath = __DIR__ . '/main.json')
    {   
        if ($verbose)
            echo 'Updating configuration file: ';

        $result = \BrunoNatali\Tools\File\JsonFile::saveArray(
            $configFilePath, 
            self::getDefaults(), 
            false,
            JSON_PRETTY_PRINT // 4 Better human visualization or hand edition 
        );

        if ($verbose)
            echo ($result ? 'OK' : 'ERROR') . PHP_EOL; 

        return $result;
    }
}

/* Leave this code to be used by hand in the future instead included
    $update = false;
    $verbose = false;

    foreach ($argv as $arg) {
        if ($arg === '--update')
            $update = true;
        if ($arg === '--verbose')
            $verbose = true;
    }

    if ($update)
        exit((MainDefaults::updateDefaults($verbose) ? 0 : 1));

    // Apply default config
    exit((MainDefaults::applyDefaults($verbose) ? 0 : 1));
*/