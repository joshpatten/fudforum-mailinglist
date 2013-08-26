#!/usr/bin/php
<?php

function userQuery($host, $user, $passwd, $dbname, $table) {
	errorReport("Opening link to MySQL database...\n");
	$mylink = mysql_connect($host, $user, $passwd)
		or die(errorReport('Could not connect: ' . mysql_error()));
	errorReport("Link created to MySQL.\n");
	mysql_select_db($dbname) or die(errorReport("Could not select database\n"));
	errorReport("Switched to database " . $dbname . "\n");
	$query = 'SELECT name, email, custom_fields FROM ' . $table;
	errorReport("performing query\n");
	$result = mysql_query($query) or die(errorReport('Query failed: ' . mysql_error()));
	$queryarray = array();
	while ($line = mysql_fetch_array($result, MYSQL_ASSOC)) {
		$queryarray[] = $line;
	}
	errorReport("Closing link to MySQL database.\n");
	mysql_close($mylink);
	errorReport("Returning results.\n");
	return $queryarray;
}

function mlQuery($host, $user, $passwd, $dbname, $table, $mladdr) {
  errorReport("Opening link to MySQL database...\n");
	$mylink = mysql_connect($host, $user, $passwd)
		or die(errorReport('Could not connect: ' . mysql_error()));
	errorReport("Link created to MySQL.\n");
	mysql_select_db($dbname) or die(errorReport("Could not select database\n"));
	errorReport("Switched to database " . $dbname . "\n");
	$query = 'SELECT id, forum_id, name, subject_regex_haystack, additional_headers FROM ' . $table . ' WHERE name="' . $mladdr . '"';
	errorReport("performing query\n");
	$result = mysql_query($query) or die(errorReport('Query failed: ' . mysql_error()));
	$queryarray = array();
	while ($line = mysql_fetch_array($result, MYSQL_ASSOC)) {
		$queryarray[] = $line;
	}
	errorReport("Closing link to MySQL database.\n");
	mysql_close($mylink);
	errorReport("Returning results.\n");
	return $queryarray;
}

function forumNameQuery($host, $user, $passwd, $dbname, $table, $forumid) {
  errorReport("Opening link to MySQL database...\n");
	$mylink = mysql_connect($host, $user, $passwd)
		or die(errorReport('Could not connect: ' . mysql_error()));
	errorReport("Link created to MySQL.\n");
	mysql_select_db($dbname) or die(errorReport("Could not select database\n"));
	errorReport("Switched to database " . $dbname . "\n");
	$query = 'SELECT id, name FROM ' . $table . ' WHERE id=' . $forumid;
	errorReport("performing query\n");
	$result = mysql_query($query) or die(errorReport('Query failed: ' . mysql_error()));
	$queryarray = array();
	while ($line = mysql_fetch_array($result, MYSQL_ASSOC)) {
		$queryarray[] = $line;
	}
	errorReport("Closing link to MySQL database.\n");
	mysql_close($mylink);
	errorReport("Returning results.\n");
	return $queryarray[0]['name'];
}

function checkMlSubscriptions($name, $email, $custom_fields, $mlID) {
	if (is_null($custom_fields) == false) {
		$custom_fields = str_replace(":", "", $custom_fields);
		$custom_fields = preg_replace('/.*\{/', '', $custom_fields);
		$custom_fields = str_replace("}", "", $custom_fields);
		$fieldarray = explode(";", $custom_fields);
		$fieldnull = array_pop($fieldarray);
		$subscribe = null;
		for ($i = 0; $i < count($fieldarray) - 1; $i = $i + 2) {
			if ($fieldarray[$i] == 'i' . $mlID) {
				$subscribe = preg_match('/\"(.*)\"/', $fieldarray[$i + 1], $matches);
				$subscribe = $matches[1];
			}
		}
		if (trim($subscribe) == "Yes") {
			$contact = $email;
			return $contact;
		} else {
			return null;
		}
	} else {
		return null;
	}
}
	
// This is used to get the mailing list email address
function getToAddr($emailarr) {
	foreach ($emailarr as $mailline) {
        if (preg_match('/^To: +/', $mailline)) {
                $toline = explode(" ", $mailline);
                $toline = end($toline);
				$toline = str_replace("<", "", $toline);
				$toline = str_replace(">", "", $toline);
				$origaddr = trim($toline);
                return $origaddr;
        }
	}
}

// This is used to mask the users email address and use the original mailing list address
// Also used to set only a single From: address, as the forum seems to send many of them
function setFromAddr($emailarr, $newfromaddr) {
	foreach ($emailarr as $mailline) {
        if (preg_match('/^From: +/', $mailline)) {
			$mailline = preg_replace('/<.*@.*>/', "<" . $newfromaddr . ">", $mailline);
			$fromaddr = $mailline;
        }
	}
	return $fromaddr;
}

// This is used to set the X-Mailer list attribute. For some reason FUDforum puts a duplicate in there
function getXMailer($emailarr) {
	foreach ($emailarr as $mailline) {
        if (preg_match('/^X-Mailer: +/', $mailline)) {
                $origaddr = $mailline;
                return $origaddr;
        }
	}
}

function errorReport($string) {
	error_log(date(DATE_RFC822) . ": " . $string, 3, "/var/log/mlist/lists.log");
}
	
include	'/usr/local/bin/mailinglists-settings.php';

//Postfix sends to STDIN
$fd = fopen("php://stdin", 'r');
$email = array();
//Read entire email from STDIN
while ( $line = fgets($fd) ) {
        $email[] = $line;
}
//Tidy Up
fclose($fd);
errorReport("Finished reading mail from stdin\n");	

//Get all users from database
errorReport("Querying users...\n");
$queryarray = userQuery($mysql_host, $mysql_username, $mysql_passwd, $mysql_dbname, $mysql_users_table);
errorReport("User query complete.\n");

//Get mailing list email address
errorReport("Getting mailing list address\n");
$mladdress = getToAddr($email);
errorReport("Mailing list address is " . $mladdress . "\n");

//Set proper From: address
errorReport("Setting proper From: address\n");
$fromaddress = setFromAddr($email, $mladdress);

//Get mailing list ID (returns array with ML info)
errorReport("Getting mailing list ID\n");
$mlinfo = mlQuery($mysql_host, $mysql_username, $mysql_passwd, $mysql_dbname, $mysql_ml_table, $mladdress);
if (empty($mlinfo) == true) {
	die(errorReport("Mailing list for adddress " . $mladdress . " not found. Aborting...\n"));
}
errorReport("Mailing list ID is " . $mlinfo[0]['id'] . "\n");
$subject_prepend = str_replace('/', "", $mlinfo[0]['subject_regex_haystack']);
$subject_prepend_new = str_replace('\\', "", $subject_prepend);
errorReport("Mailing list subject line injection is: " . $subject_prepend_new . "\n");

//Get Forum name, prepend to To: email address
errorReport("Getting Forum name...\n");
$forumname = forumNameQuery($mysql_host, $mysql_username, $mysql_passwd, $mysql_dbname, $mysql_forum_table, $mlinfo[0]['forum_id']);
errorReport("Forum name is: " . $forumname . "\n");

//Check mailing list subscriptions, create array of subscribed users
errorReport("Checking subscriptions...\n");
$maillistusers = array();
foreach ($queryarray as $user) {
	$usercheck = checkMlSubscriptions($user['name'], $user['email'], $user['custom_fields'], $mlinfo[0]['id']);
	if (is_null($usercheck) == false) {
		$maillistusers[] = $usercheck;
	}
}
//Split mailing list users into chunks of 50 so we send a new email for every 50 users so we don't hit limits...
$maillistchunks = array_chunk($maillistusers, 50);
errorReport("All subscribed users checked, added to outgoing mailling list.\n");

//Iterate through each line of the message for new message creation
errorReport("Creating new message\n");
$newemail = array();
$newemail[] = $fromaddress;
foreach ($email as $mailline) {
	if (preg_match('/^From: +/', $mailline)) {
		errorReport("Skipping extra From...\n");
	} else if (preg_match('/^Return-Path: +/', $mailline)) {
		errorReport("Fixing line " . $mailline);
		$mailline = preg_replace('/<.*@.*>/', '<' . $mladdress . '>', $mailline);
		errorReport("Line is now " . $mailline);
		$newemail[] = $mailline;
	} else if (preg_match('/^Subject: +/', $mailline)) {
		if (!preg_match('/.*' . $subject_prepend . '.*/', $mailline)) {
			$mailline = str_replace('Subject: ', 'Subject: ' . $subject_prepend_new . ' ', $mailline);
			$newemail[] = $mailline;
		} else {
			$newemail[] = $mailline;
		}
    } else if (preg_match('/^To: +/', $mailline)) {
		$maillinestr = "To: " . $forumname . " <" . $mladdress . ">\n";
		errorReport("New To: header is: " . trim($maillinestr) . "\n");
		$newemail[] = $maillinestr;
		$mlheaders = explode("\r", $mlinfo[0]['additional_headers']);
		foreach ($mlheaders as $line) {
			$newemail[] = trim($line) . "\n";
		}
	} else {
		$newemail[] = $mailline;
	}
}

$mldirname = str_replace("[", "", $subject_prepend_new);
$mldirname = str_replace("]", "", $mldirname);
$firstline = "From " . str_replace("From: ", "", trim($fromaddress)) . " " . date('D M d H:i:s Y') . "\n";
errorReport("Creating test message file\n");
$fo = fopen("/usr/local/mail/" . $mldirname . "/" . time() . ".msg", 'w');
fwrite($fo, $firstline);
foreach ($newemail as $line) {
                fwrite($fo, $line);
        }
fclose($fo);

errorReport("Creating email list for sendmail file\n");
foreach ($maillistchunks as $chunk) {
    $emaillist = null;
	foreach ($chunk as $line) {
      $emaillist = $emaillist . ' ' . $line;
    }
	//Setup for talking to sendmail
	$descriptorspec = array(
		0 => array("pipe", "r"),  // stdin is a pipe that the child will read from
		1 => array("pipe", "w"),  // stdout is a pipe that the child will write to
		2 => array("file", "/var/log/mlist/lists.log", "a") // stderr is a file to write to
	);
	//Notice sendmail.postfix and the -G option. -G sends back to postfix on the content filter submission port 10026
	//which bypasses the original content filter
	$sendmailcmd = '/usr/sbin/sendmail.postfix -G -i -f ' . $mladdress . ' --' . $emaillist;
	errorReport("Sending to mailing list\n");
	//Send back to postfix
	$process = proc_open($sendmailcmd, $descriptorspec, $pipes);
	if (is_resource($process)) {
        foreach ($newemail as $line) {
            fwrite($pipes[0], $line);
        }
        fclose($pipes[0]);
        $stream = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        proc_close($process);
	}
}

errorReport("Mailing list send complete\n");
errorReport("----------------------------------------------------------------\n");
?>
