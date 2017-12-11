<?php
/* @Name: rsa_encrypt.php
*  @Description: encrypt using RSA 
*  @author: Pradnya Pathare
*  @date: 11/12/2017
*/

	require_once('phpseclib/Crypt/RSA.php');
	require_once('phpseclib/Crypt/AES.php');
	
	//Function for encrypting with RSA
	function rsa_encrypt($string, $public_key)
	{
		//Create an instance of the RSA cypher and load the key into it
		$cipher = new Crypt_RSA();
		$cipher->loadKey($public_key);
		//Set the encryption mode
		$cipher->setEncryptionMode(CRYPT_RSA_ENCRYPTION_PKCS1);
		//Return the encrypted version
		return base64_encode($cipher->encrypt($string));
	}

	//Function for decrypting with RSA 
	function rsa_decrypt($string, $private_key)
	{
		//Create an instance of the RSA cypher and load the key into it
		$cipher = new Crypt_RSA();
		$cipher->loadKey($private_key);
		//Set the encryption mode
		$cipher->setEncryptionMode(CRYPT_RSA_ENCRYPTION_PKCS1);
		//Return the decrypted version
		return $cipher->decrypt($string);
	}
	
	$url = "https://gateway.nib-cf-test.com/join/public-key/";
	
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
	$output=curl_exec($ch);
	curl_close($ch);
	
	$res = json_decode($output);
	/*$pubkey = "-----BEGIN PUBLIC KEY-----\n".$res->PublicKey."\n-----END PUBLIC KEY-----";
	
	echo $pubkey;*/
	
	$creditcard_no = "4242424242424242";
	
	$ciphertext = rsa_encrypt($creditcard_no, $res->PublicKey);
	
	echo sprintf("<h4>Plaintext for RSA encryption:</h4><p>%s</p><h4>After encryption:</h4><p>%s</p>", $creditcard_no, $ciphertext);

?>