<?php
/*
Pickle: The Penguin Client Library
An open source PHP library to ease the development of third party Club Penguin clients.
Copyright (C) 2008 RancidKraut

library.php - OOP Interface
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

include("servers.php");

class Pickle
{
	var $room = 0;
	var $server = -1;
	var $servers;
	var $sock;
	var $status = 0;
	var $player = 0; // Can we make a Player class?
	var $_events = array();
	
	function Pickle()
	{
		global $server;
		$this->servers = $server;
	}
	
	function connect($username, $password, $server = -1)
	{
		if ($server != null)
			$this->server = $server;

		if ($this->server < 0)
			return 1;
		
		$sock = fsockopen($this->servers["login"]["ip"], $this->servers["login"]["port"]);
		fwrite($sock, "<policy-file-request/>" . chr(0));
		fwrite($sock, "<msg t='sys'><body action='verChk' r='0'><ver v='130' /></body></msg>" . chr(0));
		fwrite($sock, "<msg t='sys'><body action='rndK' r='-1'></body></msg>" . chr(0));
		
		$data = fread($sock, 8192);
		while (!(stripos($data, '</k>')))
		{
			$data .= fread($sock, 8192);
		}
		
		if (!preg_match('|<k>([0-9]*)</k>|', $data, $match)
			return 2;
		$key = generateKey($password, $match[0], true);
		
		fwrite($sock, "<msg t='sys'><body action='login' r='0'><login z='w1'><nick><![CDATA[$username]]></nick><pword><![CDATA[$key]]></pword></login></body></msg>" . chr(0));
		
		$data = fread($sock, 8192);
		while (!(stripos($data, chr(0))))
		{
			$data .= fread($sock, 8192);
		}
		
		$packet = _decode_packet($data);
		$this->player = $packet[2];
		$key = $packet[3];
		
		fclose($sock);
		$this->sock = fsockopen($this->server[$this->server]["ip"], $this->server[$this->server]["port"]);
		
		fwrite($this->sock, "<policy-file-request/>".chr(0));
		fwrite($this->sock, "<msg t='sys'><body action='verChk' r='0'><ver v='130' /></body></msg>".chr(0));
		fwrite($this->sock, "<msg t='sys'><body action='rndK' r='-1'></body></msg>".chr(0));
		
		$data = fread($this->sock, 8192);
		while (!(stripos($data, '</k>')))
		{
			$data .= fread($sock, 8192);
		}
		
		if (!preg_match('|<k>([0-9]*)</k>|', $data, $match)
			return 3;
		$key = generateKey($key, $match[0]);
		
		fwrite($sock, "<msg t='sys'><body action='login' r='0'><login z='w1'><nick><![CDATA[$username]]></nick><pword><![CDATA[{$this->key}]]></pword></login></body></msg>".chr(0));
		
		$this->_send_packet(array('s', 'j#js', -1, $this->player, $key, ''));
		$this->_send_packet(array('s', 'i#gi', -1));
		$this->_send_packet(array('s', 'n#gn', -1));
		$this->_send_packet(array('s', 'b#gb', -1));
		$this->_send_packet(array('s', 'p#gu', -1));
		
		return 0;
	}
	
	function attach_event($event, $function)
	{
		$this->_events[$event][] = $function;
	}
	
	function raise_event($event, $data)
	{
		foreach ($this->_events[$event] as $event)
		{
			call_user_func_array($event, $data);
		}
	}
	
	function process_packets()
	{
		while (!feof($this->sock))
		{
			$data .= fgets($this->sock, 8192);
		}
		
		$packets = explode(chr(0), $data);
		foreach ($packets as $packet)
		{
			if ($packet == '')
				continue;
			$p = $this->_decode_packet($packet);
			switch ($p[0])
			{
				case 'jr':
					$this->room = $p[1];
					$this->raise_event('join_room', array($p[1]);
					break;
			}
		}
	}
	
	function _generate_key($password, $key, $login = false)
	{
		if ($login)
		{
			return strtolower($this->_encrypt_password(strtoupper($this->_encrypt_password($password)).$key));
		} else {
			return strtolower(encryptPassword($password.$key).$password);
		}
	}
	
	function _encrypt_password($password)
	{
		return substr(md5($password), 16, 16) . substr(md5($password), 0, 16);
	}
	
	function _decode_packet($data)
	{
		$array = explode('%', $data);
		unset($array[0]);
		unset($array[1]);
		sort($array);
		return $array;
	}
	
	function _send_packet($data)
	{
		fwrite($this->sock, '%xt%'.implode('%', $data).chr(0);
	}
}