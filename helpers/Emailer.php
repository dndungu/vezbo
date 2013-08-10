<?php

namespace helpers;

class EmailerException extends \Exception {}

class Emailer {
	
	private $sandbox = NULL;
	
	public function __construct(&$sandbox){
		$this->sandbox = &$sandbox;
	}
	
	public function send($receivers, $subject, $message, $replyTo = null){
		try {
			$settings = $this->sandbox->getMeta('settings');
			$headers[] = "MIME-Version: 1.0";
			$headers[] = "Content-type: text/html; charset=utf-8";
			$headers[] = "From: Zatiti Support <support@zatiti.com>";
			$headers[] = "Bcc: Zatiti Support <support@zatiti.com>";
			if(!is_null($replyTo)){
				$headers[] = 'Reply-To: '. $replyTo['name'] . ' <' . $replyTo['email'] . '>';
			}
			$headers[] = "\r\n";
			foreach($receivers as $receiver){
				$to[] = "{$receiver['name']} <{$receiver['email']}>";
			}
			$recipients = implode(',', $to);
			if(mail($recipients, $subject, $message, implode("\r\n", $headers))) {
				return true;
			}
			throw new EmailerException("Failed to send email to {$recipients}");
		}catch(\Exception $e){
			throw new EmailerException($e->getMessage());
		}
	}
	
	public function text($from, $to, $text){
		try {
			$rows = $this->sandbox->getGlobalStorage()->query('SELECT * FROM `setting` WHERE `siteID` = 1');
			foreach ($rows as $row){
				$name = $row['name'];
				$$name = $row['content'];
			}
			$fields[] = 'api_key='.$nexmo_key;
			$fields[] = 'api_secret='.$nexmo_secret;
			$fields[] = 'from='.urlencode($this->formatPhoneNumber($from));
			$fields[] = 'to='.urlencode($this->formatPhoneNumber($to));
			$fields[] = 'text='.urlencode($text);
			$fields[] = 'body=0011223344556677';
			$fields[] = 'udh=06050415811581';
			$ch = curl_init('https://rest.nexmo.com/sms/json');
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, (implode('&', $fields)));
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			error_log(print_r(curl_exec($ch), true));
		} catch (\Exception $e) {
			throw new EmailerException($e->getMessage());
		}
	}
	
	private function formatPhoneNumber($phone){
		$phone = str_replace(' ', '', $phone);
		$phone = str_replace('-', '', $phone);
		$phone = str_replace('+', '', $phone);
		$phone = ltrim($phone, '0');
		return (strlen($phone) == 12) ? "+{$phone}" : "+254{$phone}";
	}
	
	public function textTwillio($sender, $receiver, $message){
		try {
			$rows = $this->sandbox->getGlobalStorage()->query('SELECT * FROM `setting` WHERE `siteID` = 1');
			foreach ($rows as $row){
				$name = $row['name'];
				$$name = $row['content'];
			}
			$settings = $this->sandbox->getMeta('settings');
			$data[] = 'From=' . urlencode($sender);
			$data[] = 'To=' . urlencode($receiver);
			$data[] = 'Body=' . urlencode($message);
			$fields = implode('&', $data);
			$url = 'https://api.twilio.com/2010-04-01/Accounts/' . $twillio_account . '/SMS/Messages.json';
			$ch = curl_init($url);
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_USERPWD, $twillio_username . ':' . $twillio_password);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			error_log(print_r(curl_exec($ch), true));
		} catch (\Exception $e) {
			throw new EmailerException($e->getMessage());
		}
	}
	
}