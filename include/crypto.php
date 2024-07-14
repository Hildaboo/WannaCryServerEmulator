<?php
	////////////////
	//// CRYPTO ////
	////////////////
	
	function xor_data(&$key, $data, $data_len)
	{
		$i = 0;
		if($data_len > 0)
		{
			do {
				$data[$i] = $data[$i] ^ $key[30];
				$modifier = $key[11] ^ $key[30];
				$key = keysch($key);
				++$i;
				$key[0] = $modifier;
			} while($i < $data_len);
		}
		return $data;
	}
	
	function keysch($key)
	{
		$result = null;
		for($i = 0; $i < 30; $i++)
		{
			$result[$i + 1] = $key[$i];
		}
		return $result;
	}
	
	function HIWORD($l)
	{
		return (($l >> 16) & 0xFFFF);
	}
	
	function wn_crc16($data, $data_len)
	{
		$len_work = $data_len;
		$result = 0;
		$data_ref = null;
		
		if($data_len <= 2)
		{
			$data_ref = $data;
		}
		else
		{
			$loop_len = $data_len >> 1;
			$len_work = $data_len - 2 * ($data_len >> 1);
			$data_ref = $data;
			
			$offset = 2;
			do {
				$ptr = unpack('v', $data_ref)[1];
				$data_ref = substr($data, $offset, 2);
				$result += $ptr;
				--$loop_len;
				$offset += 2;
			} while($loop_len);
		}
		
		if($len_work)
		{
			$result += unpack("C", $data_ref)[1];
		}
		
		$hex_result = bin2hex(pack("v", ~(($result & 0xffff) + HIWORD($result) + ((($result & 0xffff) + HIWORD($result)) >> 16))));
		$hex_result = substr($hex_result, 2, 2) . substr($hex_result, 0, 2);
		
		return $hex_result;
	}
	
	function decrypt_user_key($prv_key_path, $master_key)
	{
		// removed :]
	}
	
	function swapEndianness($bytes)
	{
		$to_split = bin2hex($bytes);
		return hex2bin(implode('', array_reverse(str_split($to_split, 2))));
	}
?>