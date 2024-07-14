<?php
	namespace DiscordWebhooksPHP;
	class Client
	{
		protected $url       = null;
		protected $username  = null;
		protected $avatar    = null;
		protected $message   = null;
		protected $upfile    = null;
		protected $flname    = null;
		protected $isEmbed   = false;
		protected $embedData = array();

	    public function __construct($url)
		{
			$this->url = $url;
		}

	    public function setUsername($username)
		{
			$this->username = $username;
		}

	    public function setAvatar($avatar)
		{
			$this->avatar = $avatar;
		}

		public function setMessage($message)
		{
			$this->message = $message;
		}

	    public function setEmbedData($embedData)
		{
			if (!is_array($embedData))
			{
				exit;
			}	
			$this->isEmbed		= true;
			$this->embedData 	= array($embedData);
		}
		
		public function setFile($upfile, $flname)
		{
			$this->upfile = $upfile;
			$this->flname = $flname;
		}

	    public function send()
		{
			if (strlen($this->message) > 2000)
			{
				exit;
			}

			$data = array(
				'content' 		=> $this->message,
				'username' 		=> $this->username,
				'avatar_url' 	=> $this->avatar,
			);

			if ($this->isEmbed)
			{
				$data['type'] 	= 'rich';
				$data['embeds'] = $this->embedData;
			}
			else
			{
				if ($this->message == null)
				{
					exit;
				}
			}
			
			$http_head = null;
			$data_json = null;
			if ($this->upfile)
			{
				$data_json = array(
					'payload_json' => json_encode($data)
				);
				$data_json['file'] = curl_file_create($this->upfile, mime_content_type($this->upfile), $this->flname);
				$http_head = array('Content-Type: multipart/form-data');
			}
			else
			{
				$data_json = json_encode($data);
				$http_head = array('Content-Type: application/json');
			}

			$curl = curl_init();
			curl_setopt($curl, CURLOPT_URL, $this->url);
			curl_setopt($curl, CURLOPT_POST, 1);
			curl_setopt($curl, CURLOPT_HTTPHEADER, $http_head);
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
			curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($curl, CURLOPT_POSTFIELDS, $data_json);

			$output = curl_exec($curl);
			$output = json_decode($output, true);
			
			curl_close($curl);
			return true;
		}
	}
?>