<?php
/*
Pickle: The Penguin Client Library
An open source PHP library to ease the development of third party Club Penguin clients.
Copyright (C) 2008 RancidKraut

tasks.php - functions for simple game tasks
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

// Non-specific server communication functions
function sendRawPacket($packet) { // Sends a raw packet ($packet) to the game server.  It may be more convenient to use a specific function below.
  global $sock;
  fwrite($sock, $packet . chr(0));
}

function readRawPacket($length = 8192) { // Returns raw packet data, up to $length bytes.
  global $sock;
  return fread($sock, $length);
}

// Purpose-specific server communication functions
function say($phrase) { // Says phrase in chat.  Keep in mind that it may be filtered server-side.
  global $myRoomID;
  sendRawPacket("%xt%s%m#sm%" . $myRoomID . "%" . $PlayerID . "%" . $phrase . "%");
}

function goto($x, $y) { // Walks to ($x, $y) coordinate in room.
  global $myRoomID;
  sendRawPacket("%xt%s%u#sp%" . $myRoomID . "%" . $x . "%" . $y . "%");
}

function gotoRoom($targetExtRoomID, $x = 0, $y = 0) { // Goes to room specified by $roomID, spawns at coordinates ($x, $y)
  global $sock, $myRoomID, $extRoomID;
  sendRawPacket("%xt%s%j#jr%" . $myRoomID . "%" . $targetExtRoomID . "%" . $x . "%" . $y . "%");
  $rawPack = fread($sock, 8192);
  while (!(stripos($rawPack, "xt%jr%"))) {
    $rawPack = fread($sock, 8192);
  }
  $myRoomID = stribet($rawPack, "%xt%jr%", "%");
  $extRoomID = stribet($rawPack, "%xt%jr%" . $myRoomID . "%", "%");
}
?>