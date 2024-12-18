<?php

namespace docker {
    function adminer_object()
    {
        require_once('plugins/plugin.php');

        class Adminer extends \AdminerPlugin
        {
            public function login($login, $password)
            {
                return true;
            }
            public function credentials()
            {
                // server, username and password for connecting to database
                return array(
                    getenv('ADMINER_DEFAULT_SERVER') ?? 'database',
                    getenv('ADMINER_DEFAULT_USER') ?? 'root',
                    getenv('ADMINER_DEFAULT_PASSWORD') ?? ''
                );
            }
        }

        $plugins = [];
        foreach (glob('plugins-enabled/*.php') as $plugin) {
            $plugins[] = require($plugin);
        }

        return new Adminer($plugins);
    }
}

namespace {
    function adminer_object()
    {
        return \docker\adminer_object();
    }

    require('adminer.php');
}
