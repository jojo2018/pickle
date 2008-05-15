<?php
/*
Pickle: The Penguin Client Library
An open source PHP library to ease the development of third party Club Penguin clients.
Copyright (C) 2008 RancidKraut

core.php - Required core functions
This file is part of Pickle: The Penguin Client Library.

Pickle is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

// Include necessary files, required for core operations
require "servers.php";

// Generic utility functions
function stribet($inputstr, $delimiterLeft, $delimiterRight) { // Returns substring of $inputstr between $delimiterLeft and $delimiterRight
  $posLeft = stripos($inputstr, $delimiterLeft) + strlen($delimiterLeft);
  $posRight = stripos($inputstr, $delimiterRight, $posLeft);
  return substr($inputstr, $posLeft, $posRight - $posLeft);
}

// Login infrastructure functions
function encryptPassword($Password) { // Encrypts the password to be used when sending to server
  return substr(md5($Password), 16, 16) . substr(md5($Password), 0, 16);
}

function generateKey($Password, $randKey, $isLogin, $LoginKey = NULL) { // Generates the key required by the server for connection
  if ($isLogin) {
    if ($Password && $randKey) {
      return strtolower(encryptPassword(strtoupper(encryptPassword($Password)) . $randKey));
    } else {
      if (!$Password) {
        die("PCL Error: No \$Password declared in function generateKey\n");
      }
      if (!$randKey) {
        die("PCL Error: No \$randKey declared in function generateKey\n");
      }
    }
  } else {
    if (!$LoginKey) {
      die("PCL Error: No \$LoginKey declared in function generateKey\n");
    } else {
      return strtolower(encryptPassword($LoginKey . $randKey) . $LoginKey);
    }
  }
}

function doLoginSequence($serverID, $Username, $Password) { // Preforms the socket login sequence to the Club Penguin game server specified by $serverID with user $Username and pass $Password. Retruns true if successful.
  global $sock, $server, $PlayerID;
  $sock = fsockopen($server["login"]["ip"], $server["login"]["port"]);

  fwrite($sock, "<policy-file-request/>" . chr(0));
  fwrite($sock, "<msg t='sys'><body action='verChk' r='0'><ver v='130' /></body></msg>" . chr(0));

  fwrite($sock, "<msg t='sys'><body action='rndK' r='-1'></body></msg>" . chr(0));
  $rawPack = fread($sock, 8192);
  while (!(stripos($rawPack, "msg t='sys'><body action='rndK' r='-1'>"))) {
    $rawPack = fread($sock, 8192);
  }
  $randKey = stribet($rawPack, "<k>", "</k>");

  fwrite($sock, "<msg t='sys'><body action='login' r='0'><login z='w1'><nick><![CDATA[" . $Username . "]]></nick><pword><![CDATA[" . generateKey($Password, $randKey, true) . "]]></pword></login></body></msg>" . chr(0));
  $rawPack = fread($sock, 8192);
  while (!(stripos($rawPack, "xt%l%-1%"))) {
    $rawPack = fread($sock, 8192);
  }
  $PlayerID = stribet($rawPack, "%xt%l%-1%", "%");
  $LoginKey = stribet($rawPack, "%xt%l%-1%$PlayerID%", "%");

  fclose($sock);
  $sock = fsockopen($server[$serverID]["ip"], $server[$serverID]["port"]);

  fwrite($sock, "<policy-file-request/>" . chr(0));
  fwrite($sock, "<msg t='sys'><body action='verChk' r='0'><ver v='130' /></body></msg>" . chr(0));

  fwrite($sock, "<msg t='sys'><body action='rndK' r='-1'></body></msg>" . chr(0));
  $rawPack = fread($sock, 8192);
  while (!(stripos($rawPack, "msg t='sys'><body action='rndK' r='-1'>"))) {
    $rawPack = fread($sock, 8192);
  }
  $randKey = stribet($rawPack, "<k>", "</k>");

  fwrite($sock, "<msg t='sys'><body action='login' r='0'><login z='w1'><nick><![CDATA[" . $Username . "]]></nick><pword><![CDATA[" . generateKey($Password, $randKey, false, $LoginKey) . "]]></pword></login></body></msg>" . chr(0));
  fwrite($sock, "%xt%s%j#js%-1%$PlayerID%$LoginKey%%" . chr(0));
  fwrite($sock, "%xt%s%i#gi%-1%" . chr(0));
  fwrite($sock, "%xt%s%n#gn%-1%" . chr(0));
  fwrite($sock, "%xt%s%b#gb%-1%" . chr(0));
  fwrite($sock, "%xt%s%p#gu%-1%" . chr(0));

  return true;
}

function connect($serverID, $Username, $Password, $verbose = false) { // Preforms proper login with $Username/$Password and retrieves important data for later.  Returns true if successful.
  global $sock, $server, $myRoomID, $extRoomID;
  if ($verbose) {
    echo "Connecting to " . $server[$serverID]["name"] . ".\n";
    if ($server[$serverID]["safe"] == true) {
      echo "  SafeChat enabled on this server.\n";
    } else {
      echo "  SafeChat disabled on this server.\n";
    }
  }
  if (doLoginSequence($serverID, $Username, $Password)) {
    $rawPack = fread($sock, 8192);
    while (!(stripos($rawPack, "xt%jr%"))) {
      $rawPack = fread($sock, 8192);
    }
    $myRoomID = stribet($rawPack, "%xt%jr%", "%");
    $extRoomID = stribet($rawPack, "%xt%jr%" . $myRoomID . "%", "%");
    return true;
  } else { // If doLoginSequence fails
    die("PCL Error: Unknown failure in function doLoginSequence\n");
  }
}

// Post-connection server utility functions
function disconnect() { // Closes connection to the game server
  global $sock;
  fclose($sock);
}
?>