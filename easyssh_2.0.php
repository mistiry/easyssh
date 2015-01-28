#!/usr/bin/php
<?php
/**
 * EasySSH v2.0
 * --------------------------------
 * This script utilizes the libssh2 libraries to enable PHP to establish SSHv2 connections
 *
 * Author: Mark Lewis
 * Revision Date: 12/17/2014
 *
 * Change Notes:
 * v1.7 -> v2.0
 *     - Changed method for obtaining parameters from old 'for' loop method to getopt()
 *     - Removed some debug code
 *     - Changed method for verifying required parameters
 *     - Removed default blank values for variables to require all parameters each run
**/

error_reporting(!E_WARNING | E_ERROR | !E_NOTICE);

//Colors class for colorized output
include("colors.php");
$colors = new Colors();

//Command Line Parameters
$opts = "";
$opts.= "t:"; //Target
$opts.= "p:"; //Port
$opts.= "a:"; //Password Chunk
$opts.= "A:"; //Password
$opts.= "u:"; //Username
$opts.= "m:"; //Program Mode
$opts.= "r:"; //Mode Parameter, to give a parameter to the program mode
$opts.= "f:"; //Enable file support to read in parameters from a file
$options = getopt($opts);

//Defaults
$basedir = "/home/mlewis/Projects/easyssh";
$permissions = 0744;
$error = "";

//Set variables to passed parameters
$target = $options['t'];
$port = $options['p'];
$password = $options['A'];
$passwordchunk = $options['a'];
$username = $options['u'];
$mode = $options['m'];
$modeparam = $options['r'];
$paramfile = $options['f'];

//Parameter verification for required options, ignore if 'f' parameter sent
if($paramfile == "") {
	$isset_t = ($target != "" ? $target = $options['t'] : $error.="Target not specified!\n");
	$isset_p = ($port != "" ? $port = $options['p'] : $error.="Port not specified!\n");
	$isset_a = ($passwordchunk != "" ? $passwordchunk = $options['a'] : ($password != "" ? $password = $options['A'] : $error.="Password not specified!\n"));
	$isset_u = ($username != "" ? $username = $options['u'] : $error.="Username not specified!\n");
	$isset_m = ($mode != "" ? $mode = $options['m'] : $error.="Mode not specified!\n");
} else {
	$isset_a = ($passwordchunk != "" ? $passwordchunk = $options['a'] : ($password != "" ? $password = $options['A'] : $error.="Password not specified!\n"));
	$isset_m = ($mode != "" ? $mode = $options['m'] : $error.="Mode not specified!\n");
}

//If we wrote an error message to the $error variable, die with the errors
if($error != "") {
	die($error);
}

//If we've made it here, we know we have enough info to start doing stuff
if($paramfile != "") {
	echo "Loading parameter file '$paramfile'...\n";
	if(is_readable($paramfile)) {
		$file = file($paramfile);
	} else {
		die("Unable to read '$paramfile'\n");
	}
	foreach($file as $line) {
		if(substr($line,0,1) === "#") {
			continue;
		}
		$pieces = explode(":",$line);
		$target = trim($pieces[0]);
		$port = trim($pieces[1]);
		$username = trim($pieces[2]);
		
		startProcess($target,$port,$username,$password,$passwordchunk,$mode,$modeparam);
		
	}
} else {
	startProcess($target,$port,$username,$password,$passwordchunk,$mode,$modeparam);
}



// FUNCTIONS
function startProcess($target,$port,$username,$password,$passwordchunk,$mode,$modeparam) {
	global $colors;
	global $basedir;
	global $permissions;
	
	if($password !="" && $passwordchunk != "") {
		die("Cannot begin processing, please only pass -a OR -A!\n");
	}
	
	$setpassvar = ($password == "" ? $loginpass = "Ro" . $passwordchunk . "!T" : $loginpass = $password);
	
	echo "Connecting to '$target'...";
	$ssh = sshConnect($target,$port,$username,$loginpass);
	
	//MODES
	//@todo - this needs to be better code
	if($mode == "DOWNLOAD") {
		echo "\tDOWNLOAD: '$modeparam'...";
		if(!is_dir("$basedir/files/$target")) {
			mkdir("$basedir/files/$target");
		}
		$pieces = explode("/",$modeparam);
		$filename = array_pop($pieces);
		if(ssh2_scp_recv($ssh,"$modeparam","$basedir/files/$target/$filename")) {
			echo $colors->getColoredString("File saved to $basedir/files/$target/$filename\n","green",null);
		} else {
			echo $colors->getColoredString("FAILED!\n","red",null);
		}
	}
	if($mode == "UPLOAD") {
		echo "\tUPLOAD: '$modeparam'...";
		$pieces = explode("/",$modeparam);
		$filename = array_pop($pieces);
		if(ssh2_scp_send($ssh,"$basedir/files/$target/$filename","$modeparam",$permissions)) {
				echo $colors->getColoredString("File uploaded to '$modeparam'\n","green",null);
		} else {
				echo $colors->getColoredString("FAILED!\n","red",null);
		}
	}
	if($mode == "EXECNOOUTPUT") {
		echo "\tEXECNOOUTPUT: '$modeparam'...";
		if(ssh2_exec($ssh,"$modeparam")) {
			echo $colors->getColoredString("SUCCESS!\n","green",null);
		} else {
			echo $colors->getColoredString("FAILED!\n","red",null);
		}
	}
	if($mode == "EXEC") {
		echo "\tEXEC: '$modeparam'...\n";
		if($stream = ssh2_exec($ssh,"$modeparam")) {
			stream_set_blocking($stream,true);
			echo $colors->getColoredString(stream_get_contents($stream),"green",null);
		} else {
			echo $colors->getColoredString("FAILED!\n","red",null);
		}
	}
}

function sshConnect($target,$port,$username,$loginpass) {
	global $colors;
	$ssh = ssh2_connect($target,$port);
	if($ssh && ssh2_auth_password($ssh,$username,$loginpass)) {
		echo $colors->getColoredString("OK!\n","green",null);
		return $ssh;
	} else {
		echo $colors->getColoredString("FAIL!\n","red",null);
		//exit(1);
	}
}