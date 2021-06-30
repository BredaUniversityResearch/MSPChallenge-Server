<?php

$codeBranch = '/';

$GLOBALS['config'] = array(
						'code_branch'	=> $codeBranch,
						'mysql'      	=> array(
							'host'         => 'localhost',
							'username'     => 'root',
							'password' => '',
							'db'           => 'msp_server_manager',
							'multisession_database_prefix' => 'msp_session_'),
						'remember'        => array(
						  'cookie_name'   	=> 'pmqesoxiw318374csb',
						  'cookie_expiry' 	=> 604800),
						'session' => array(
						  'session_name' 	=> 'user',
						  'token_name' 		=> 'token'),
						'msp_auth'				=> array(
							'with_proxy' 			=> ""),
						'msp_server_protocol'	=> 'http://',	// change to https to go secure with all background connections to your MSP Challenge servers
						'msp_servermanager_protocol' => 'http://' // change to https to go secure with all background connections to your MSP Challenge Server Manager
					);
