#!/usr/bin/php
<?php
// EasySSH v1.7 - This script utilizes the libssh2 library in order to make SSH connections
// directly with PHP.
// Author: Mark H. Lewis Jr. 
error_reporting(!E_WARNING | E_ERROR | !E_NOTICE);
include("colors.php");
$colors = new Colors();

//Defaults
$basedir = "/home/mlewis/Projects/easyssh";
$target = "";
$password = "";
$passwordchunk = "";
$port = "22";
$mode = "";
$username = "root";
$permissions = 0744;
/* Get parameters, available ones are:
--target [target]			The target machine
--port [number]				The SSH port
--password [password]		The SSH password
--passwordchunk [password]	The SSH password, encapsulated by our standard prefix/suffix
--username [username]		The SSH username
--mode						The mode we wish to use, available options are:
								DOWNLOAD	Download a file from the target
								UPLOAD  	Upload a file to the target
								UPLOADNEW	Upload a file to the target as a .new file
								ROLLFILE	Roll a file into place
								SHOWDIFFS	Show diffs on the target for the specified file and its associated .new file
								RUNNING		Check if a process is running on the target
								RESTART		Restart a service on the target
								CURLCHECK	Check if a server answers SSLv3 (this command only runs locally, it doesn't SSH anywhere)
								EXEC		Execute a command on target
								MYSQLPWCHK	Check which MySQL password is in use at target
								MAXCLIENTS	Check if target has the MaxClients error in /var/log/httpd/error_log
*/

for($i=0;$i<$argc;$i++) {
	if(strstr($argv[$i],"--target")) {
        $j = $i + 1;
        $target = $argv[$j];
    }
    if(strstr($argv[$i],"--password")) {
        $j = $i + 1;
		$password = $argv[$j];
    }
    if(strstr($argv[$i],"--passwordchunk")) {
		$j = $i + 1;
		$password = "Ro" . $argv[$j] . "!T";
    }
    if(strstr($argv[$i],"--port")) {
        $j = $i + 1;
        $port = $argv[$j];
    }
    if(strstr($argv[$i],"--username")) {
		$j = $i + 1;
		$username = $argv[$j];
    }
    if(strstr($argv[$i],"--mode")) {
		$j = $i + 1;
		$k = $i + 2;
		$mode = $argv[$j];
		$param = $argv[$k];
    }
	if(strstr($argv[$i],"--permissions")) {
		$j = $i + 1;
		$permissions = intval($argv[$j],8);
	}
}

//Clear the screen, makes for easier output
//system("clear");

//Make sure we have all our required args, error if not
$error = "";
if($target == "") {
		$error.= "No target specified.\n";
}
if($password == "") {
		$error.= "No password specified.\n";
}
if($mode == "") {
		$error.= "No mode specified.\n";
}
if($error != "") {
		die($error);
}

echo "ssh $username@$target -P $port :: ";

if($mode == "DOWNLOAD") {
		if(!is_dir("$basedir/files/$target")) {
				mkdir("$basedir/files/$target");
		}
		$pieces = explode("/",$param);
		$filename = array_pop($pieces);
		echo "DOWNLOAD $param :: ";
		$ssh = ssh2_connect($target,$port);
		if($ssh && ssh2_auth_password($ssh,$username,$password)) {
				if(ssh2_scp_recv($ssh,$param,"$basedir/files/$target/$filename")) {
						echo $colors->getColoredString("OK!\n","green",null);
				} else {
						echo $colors->getColoredString("FAILED!\n","red",null);
				}
		} else {
				echo $colors->getColoredString("CONNECTION FAILURE!\n","yellow",null);
		}
}
if($mode == "UPLOAD") {
		$pieces = explode("/",$param);
		$filename = array_pop($pieces);
		echo "UPLOAD $param :: ";
		$ssh = ssh2_connect($target,$port);
		if($ssh && ssh2_auth_password($ssh,$username,$password)) {
				if(ssh2_scp_send($ssh,"$basedir/files/$target/$filename","$param",$permissions)) {
						echo $colors->getColoredString("OK!\n","green",null);
				} else {
						echo $colors->getColoredString("FAILED!\n","red",null);
				}
		} else {
				echo $colors->getColoredString("CONNECTION FAILURE!\n","yellow",null);
		}
}
if($mode == "UPLOADNEW") {
		$pieces = explode("/",$param);
		$filename = array_pop($pieces);
		echo "UPLOADNEW $param :: ";
		$ssh = ssh2_connect($target,$port);
		if($ssh && ssh2_auth_password($ssh,$username,$password)) {
				if(ssh2_scp_send($ssh,"$basedir/files/$target/$filename","$param.new",$permissions)) {
						echo $colors->getColoredString("OK!\n","green",null);
				} else {
						echo $colors->getColoredString("FAILED!\n","red",null);
				}
		} else {
				echo $colors->getColoredString("CONNECTION FAILURE!\n","yellow",null);
		}
}
if($mode == "ROLLFILE") {
		echo "ROLLFILE $param :: ";
		$ssh = ssh2_connect($target,$port);
		if($ssh && ssh2_auth_password($ssh,$username,$password)) {
				$stream = ssh2_exec($ssh,"/usr/sbin/rollfile.sh $param | grep installed");
				stream_set_blocking($stream,true);
				$data = trim(stream_get_contents($stream));
				if($data != "") {
						echo $colors->getColoredString("OK!\n","green",null);
				} else {
						echo $colors->getColoredString("FAILED!\n","red",null);
				}
		} else {
				echo $colors->getColoredString("CONNECTION FAILURE!\n","yellow",null);
		}
}
if($mode == "SHOWDIFFS") {
		echo "SHOWDIFFS $param :: \n";
		$ssh = ssh2_connect($target,$port);
		if($ssh && ssh2_auth_password($ssh,$username,$password)) {
				$stream = ssh2_exec($ssh,"diff $param $param.new");
				stream_set_blocking($stream,true);
				$data = stream_get_contents($stream);
				$pieces = explode("\n",$data);
				foreach($pieces as $piece) {
						echo "\t$piece\n";
				}
				//echo "\n$data\n\n";
		} else {
				echo $colors->getColoredString("CONNECTION FAILURE!\n","yellow",null);
		}
}
if($mode == "RESTART") {
		echo "RESTART $param :: ";
		$ssh = ssh2_connect($target,$port);
		if($ssh && ssh2_auth_password($ssh,$username,$password)) {
				$stream = ssh2_exec($ssh,"service $param restart | grep 'Starting httpd'");
				stream_set_blocking($stream,true);
				$data = trim(stream_get_contents($stream));
				if(stristr($data,"OK")) {
						echo $colors->getColoredString("OK!\n","green",null);
				} else {
						echo $colors->getColoredString("FAILED!\n","red",null);
				}
		} else {
				echo $colors->getColoredString("CONNECTION FAILURE!\n","yellow",null);
		}
}
if($mode == "RUNNING") {
		echo "RUNNING $param :: ";
		$ssh = ssh2_connect($target,$port);
		if($ssh && ssh2_auth_password($ssh,$username,$password)) {
				$stream = ssh2_exec($ssh,"service $param status | grep 'running'");
				stream_set_blocking($stream,true);
				$data = trim(stream_get_contents($stream));
				if($data != "") {
						echo $colors->getColoredString("RUNNING!\n","green",null);
				} else {
						echo $colors->getColoredString("NOT RUNNING!\n","red",null);
				}
		} else {
				echo $colors->getColoredString("CONNECTION FAILURE!\n","yellow",null);
		}
}
if($mode == "CURLCHECK") {
		echo "CURLCHECK :: ";
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_URL,"https://$target");
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($curl, CURLOPT_SSLVERSION, 3);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		$result = curl_exec($curl);
		curl_close($curl);
		if($result == "") {
				echo $colors->getColoredString("SSLv3 DISABLED!\n","green",null);
		} else {
				echo $colors->getColoredString("SERVER SUPPORTS SSLv3!\n","red",null);
		}
}
if($mode == "EXEC") {
		echo "EXEC $param :: ";
		$ssh = ssh2_connect($target,$port);
		if($ssh && ssh2_auth_password($ssh,$username,$password)) {
				if(ssh2_exec($ssh,"$param")) {
						echo $colors->getColoredString("SUCCESS!\n","green",null);
				} else {
						echo $colors->getColoredString("FAILED!\n","red",null);
				}
		} else {
				echo $colors->getColoredString("CONNECTION FAILURE!\n","yellow",null);
		}		
}
if($mode == "MYSQLPWCHK") {
		echo "MYSQLPWCHK :: ";
		$ssh = ssh2_connect($target,$port);
		if($ssh && ssh2_auth_password($ssh,$username,$password)) {
				$cmd1 = "echo 'show full processlist' | mysql -uroot -pets-rpmg";
				$cmd2 = "echo 'show full processlist' | mysql -uroot mysql -pyankees4512";
				$cmd3 = "echo 'show full processlist' | mysql -uroot";
				$result1 = ssh2_exec($ssh,$cmd1);
				$result2 = ssh2_exec($ssh,$cmd2);
				$result3 = ssh2_exec($ssh,$cmd3);
				stream_set_blocking($result1,true);
				stream_set_blocking($result2,true);
				stream_set_blocking($result3,true);
				$data1 = stream_get_contents($result1);
				$data2 = stream_get_contents($result2);
				$data3 = stream_get_contents($result3);
				//var_dump($data1);
				//var_dump($data2);
				//var_dump($data3);
				if(!stristr($data1,"processlist")) {
						if(!stristr($data2,"processlist")) {
								if(!stristr($data3,"processlist")) {
										echo $colors->getColoredString("UNKNOWN!\n","red",null);
								} else {
										echo $colors->getColoredString("BLANK PASSWORD!\n","yellow",null);
								}
						} else {
								echo $colors->getColoredString("INTELLIFUELS PASSWORD!\n","green",null);
						}
				} else {
						echo $colors->getColoredString("SPECTRUM PASSWORD!\n","green",null);
				}
		} else {
				echo $colors->getColoredString("CONNECTION FAILURE!\n","yellow",null);
		}
}
if($mode == "MAXCLIENTS") {
		echo "MAXCLIENTS :: ";
		$ssh = ssh2_connect($target,$port);
		if($ssh && ssh2_auth_password($ssh,$username,$password)) {
				$stream = ssh2_exec($ssh,"grep -i 'consider raising the MaxClients setting' /var/log/httpd/error_log");
				stream_set_blocking($stream,true);
				$data = trim(stream_get_contents($stream));
				if($data == "") {
						echo $colors->getColoredString("OK!\n","green",null);
				} else {
						echo $colors->getColoredString("TARGET HAS MAXCLIENTS ERROR!\n","red",null);
				}
		} else {
				echo $colors->getColoredString("CONNECTION FAILURE!\n","yellow",null);
		}
}
function showHelp() {
    echo "Write some damn help info, Mark.\n";
    die;
}
?>