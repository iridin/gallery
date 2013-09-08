<?php
	include "config.php";

	function my_error() {
		echo 'ERROR';
	}
	
	function validate() {
		GLOBAL $gallery;
				
		// If not password protected return true
		if(strlen($gallery['password']) == 0)
			return true;
		
		$sql = connect();
		
		// get ticket from cookie
		if(!isset($_COOKIE['ticket']))
			return false;
		$ticket = $sql->escape_string($_COOKIE['ticket']);
		
		// validate ticket
		$result = $sql->query("SELECT * FROM ticket WHERE ticket='$ticket'") or die(my_error());
		if($result->num_rows == 1)
		{
			// update time
			$sql->query("UPDATE ticket SET time=CURRENT_TIMESTAMP WHERE ticket='$ticket'") or die(my_error());
			return true;
		}
		
		return false;
	}
	
	function is_logged() {
		return isset($_COOKIE['ticket']);
	}
	
	function genchallenge() {
		$sql = connect();

		// gen challenge data
		$challenge = $sql->escape_string(rand());
		$address = $_SERVER['REMOTE_ADDR'];
		
		// wipe old challenges for address
		$sql->query("DELETE FROM challenge WHERE address='$address'");
		
		// store challenge to database
		$sql->query("INSERT INTO challenge ( challenge, address ) VALUES ( '$challenge', '$address' )");
		
		return $challenge;
	}

	function login($response) {
		GLOBAL $gallery;

		$sql = connect();
		
		$address = $_SERVER['REMOTE_ADDR'];
		
		// get salt from database
		$salt = "";		
		$result = $sql->query("SELECT challenge FROM challenge WHERE address='$address'");
		if(($result) && ($result->num_rows > 0)) {
			$challenge = $result->fetch_assoc();
			$salt = $challenge['challenge'];
		} else return false;
		
		// remove all challenges for address
		$sql->query("DELETE FROM challenge WHERE address='$address'");
		
		$plaintext = $salt . $gallery['password'];
		$cyphertext = hash("sha512", $plaintext);
		
		
		
		// check response and add ticket
		if($response == $cyphertext) {
			// prepare ticket
			$ticket = rand();
			$user = 0;
			
			
			// add ticket to database and cookies
			$sql->query("INSERT INTO ticket ( ticket, user, address ) VALUES ( '$ticket', '$user', '$address' )") or die(my_error());
			setcookie('ticket', $ticket, time()+60*60*24*365*10);
			
			return true;
		} else {
			return false;
		}
	}
	
	function logout() {
		if(!isset($_COOKIE['ticket'])) {
			return;
		} else {
			$sql = connect();
		
			$ticket = $sql->escape_string($_COOKIE['ticket']);
			setcookie('ticket','');
			$sql->query("DELETE FROM ticket WHERE ticket='$ticket'");
		}
	}
	
	function connect() {
		GLOBAL $gallery;
		return new mysqli("p:" . $gallery['sql_host'], $gallery['sql_user'], $gallery['sql_pass'], $gallery['sql_db']);
	}
	
	function get_folders() {
		GLOBAL $gallery;
		// get list of folders
		$folders = array();
		if ($handle = opendir($gallery['root'])) {
			while (false !== ($entry = readdir($handle))) {
				if ($entry != "." && $entry != ".." && !preg_match('/^\..*/',$entry)) {
					$folders[] = $entry;
					// && is_dir('.images/' . $entry)
				}
			}
			closedir($handle);
		}	
		sort($folders);
		
		return $folders;
	}
	
	function get_images($folder) {
		GLOBAL $gallery;
		// get list of images
		$images = array();
		if ($handle = opendir($gallery['root'] . "/" . $folder)) {
			while (false !== ($entry = readdir($handle))) {
				if ($entry != "." && $entry != ".." && preg_match('/.*\.([jJ][pP][gG]|[jJ][pP][eE][gG])/',$entry)) {
					$images[] = $entry;
				}
			}
			closedir($handle);
		}	
		sort($images);
		
		return $images;
	}
	
	function makepreview($folder, $image) {
		GLOBAL $gallery;
		$source = $gallery['root'] . "/" . $folder . "/" . $image;
		$key = $folder . "/" . $image;
		$name = hash("sha512", $folder . "/" . $image);
		$small = $gallery['preview'] . "/" . $name . ".jpg";
		
		// small preview
		if(!file_exists($small) || (filemtime($small) < filemtime($source))) {
		//	system('convert "' . $source . '" -resize "128x128>" -compress JPEG -quality 60 -colorspace RGB +profile "*" "' . $small . '"');
			system('convert -thumbnail 128x128 "' . $source . '" "' . $small . '"');
			touch($small);
		}

		return $small;
	}
	
	function makeweb($folder, $image) {
		GLOBAL $gallery;
		$source = $gallery['root'] . "/" . $folder . "/" . $image;
		$name = hash("sha512", $folder . "/" . $image);
		$web = $gallery['webquality'] . "/" . $name . ".jpg";
		
		// web quality
		//echo filemtime($web) . " < " . filemtime($source);
		if(!file_exists($web) || (filemtime($web) < filemtime($source))) {
			system('convert "' . $source . '" -resize "1024x768>" -compress JPEG -quality 80  "' . $web . '"');
			touch($web);
		}
		
		return $web;
	}
	
	function formatBytes($bytes, $precision = 2) { 
		$units = array('B', 'KB', 'MB', 'GB', 'TB'); 

		$bytes = max($bytes, 0); 
		$pow = floor(($bytes ? log($bytes) : 0) / log(1024)); 
		$pow = min($pow, count($units) - 1); 

		$bytes /= (1 << (10 * $pow)); 

		return round($bytes, $precision) . ' ' . $units[$pow];
	}

?>
