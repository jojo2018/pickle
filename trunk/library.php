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

$Id$

*/

class Pickle
{
	var $room = 0;
	var $server = -1;
	var $servers;
	var $sock;
	var $status = 0;
	var $player = 0; // Can we make a Player class?
	var $key = '';
	var $players = array();
	var $_events = array();
	var $run = false;
	
	function Pickle()
	{
		global $server;
		$this->servers = parse_ini_file('servers.ini', true);
	}
	
	function server_id($name)
	{
		foreach ($this->servers as $value => $server)
		{
			if ($server['name'] == $name)
			{
				return $value;
			}
		}
		return -1;
	}
	
	function start()
	{
		while($this->run)
		{
			$this->process_packets();
			$this->raise_event('tick', array());
			sleep(1);
		}
		fclose($this->sock);
	}
	
	function stop()
	{
		$this->run = false;
		return true;
	}
	
	function connect($username, $password, $server = -1)
	{
		if ($server != null)
			$this->server = $server;
			
		if (!is_numeric($this->server))
			$this->server = $this->server_id($this->server);

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
		
		if (!preg_match('|<k>(.*)</k>|', $data, $match))
			return 2;
		$key = $this->_generate_key($password, $match[1], true);
		
		fwrite($sock, "<msg t='sys'><body action='login' r='0'><login z='w1'><nick><![CDATA[$username]]></nick><pword><![CDATA[$key]]></pword></login></body></msg>" . chr(0));
		
		$data = fread($sock, 8192);
		while (!(stripos($data, chr(0))))
		{
			$data .= fread($sock, 8192);
		}
		
		$packet = $this->_decode_packet($data);
		if ($packet[0] == 'e')
			return $packet[2];
		$this->player = $this->load_player($packet[2]);
		$this->key = $packet[3];
		
		fclose($sock);
		$this->sock = fsockopen($this->servers[$this->server]["ip"], $this->servers[$this->server]["port"]);
		
		fwrite($this->sock, "<policy-file-request/>".chr(0));
		fwrite($this->sock, "<msg t='sys'><body action='verChk' r='0'><ver v='130' /></body></msg>".chr(0));
		fwrite($this->sock, "<msg t='sys'><body action='rndK' r='-1'></body></msg>".chr(0));
		
		$data = fread($this->sock, 8192);
		while (!(stripos($data, '</k>')))
		{
			$data .= fread($this->sock, 8192);
		}
		
		if (!preg_match('|<k>(.*)</k>|', $data, $match))
			return 3;
		$key = $this->_generate_key($lkey, $match[1]);
		
		fwrite($this->sock, "<msg t='sys'><body action='login' r='0'><login z='w1'><nick><![CDATA[$username]]></nick><pword><![CDATA[$key]]></pword></login></body></msg>".chr(0));
		
		$this->run = true;
		return 0;
	}
	
	function attach_event($event, $function)
	{
		$this->_events[$event][] = $function;
	}
	
	function raise_event($event, $data)
	{
		if (!isset($this->_events[$event]))
			return false;
		foreach ($this->_events[$event] as $event)
		{
			call_user_func_array($event, array_merge(array($this), $data));
		}
	}
	
	function process_packets()
	{
		while (!(stripos($data, chr(0))))
		{
			$data .= fread($this->sock, 8192);
		}
		
		$packets = explode(chr(0), $data);
		foreach ($packets as $packet)
		{
			$p = $this->_decode_packet($packet);
			if ($p === false)
				continue;
			switch ($p[0])
			{
				case 'l': // Login Complete
					$this->_send_packet(array('s', 'j#js', -1, $this->player['id'], $this->key, ''));
					break;
				case 'js': // Join Server
					$this->_send_packet(array('s', 'i#gi', -1));
					$this->_send_packet(array('s', 'n#gn', -1));
					$this->_send_packet(array('s', 'b#gb', -1));
					$this->_send_packet(array('s', 'p#gu', -1));
					echo "Fire 2\n";
					break;
				case 'lp': // Load Player
					$this->player = $this->load_player($p[2]);
					$this->raise_event('load_player', array($this->player));
					break;
				case 'jr': // Join Room
					$this->room = $p[1];
					unset($p[count($p) - 1], $p[0], $p[1], $p[2]);
					$players = array_values($p);
					foreach ($players as $player)
					{
						$x = $this->load_player($player);
						$this->players[$x['id']] = $x;
					}
					$this->raise_event('join_room', array($p[1], $this->players));
					break;
				case 'ap': // Add Player
					$player = $this->load_player($p[2]);
					$this->players[$x['id']] = $player;
					$this->raise_event('player_joined', array($player));
					break;
				case 'rp': // Remove Player
					if (isset($this->players[$p[2]]))
					{
						$player = $this->players[$p[2]];
						unset($this->players[$p[2]]);
						$this->raise_event('player_left', array($player));
					}
					break;
				case 'sp': // Send Position
					$this->players[$p[2]]['x'] = $p[3];
					$this->players[$p[2]]['y'] = $p[4];
					$this->raise_event('player_moved', array($p[2], $p[3], $p[4]));
					break;
				case 'up': // Update Player
					$player = $this->load_player($p[2]);
					$this->players[$x['id']] = $player;
					$this->raise_event('player_updated', array($player));
				default:
					$this->raise_event('unknown_packet', array($p));
			}
		}
	}
	
	function load_player($data)
	{
		if (is_numeric($data))
			return array('id' => $data);
		$data = explode('|', $data);
		$player = array();
		$player['id'] = $data[0];
		$player['username'] = $data[1];
		$player['color'] = $data[2];
		$player['head'] = $data[3];
		$player['face'] = $data[4];
		$player['neck'] = $data[5];
		$player['body'] = $data[6];
		$player['hand'] = $data[7];
		$player['feet'] = $data[8];
		$player['flag'] = $data[9];
		$player['photo'] = $data[10];
		$player['x'] = $data[11];
		$player['y'] = $data[12];
		$player['f12'] = $data[13];
		$player['member'] = $data[14];
		return $player;
	}
	
	function _generate_key($password, $key, $login = false)
	{
		if ($login)
		{
			return strtolower($this->_encrypt_password(strtoupper($this->_encrypt_password($password)).$key));
		} else {
			return strtolower($this->_encrypt_password($password.$key).$password);
		}
	}
	
	function _encrypt_password($password)
	{
		return substr(md5($password), 16, 16) . substr(md5($password), 0, 16);
	}
	
	function _decode_packet($data)
	{
		$array = explode('%', $data);
		if ($array[1] != 'xt')
			return false;
		unset($array[0]);
		unset($array[1]);
		return array_values($array);
	}
	
	function _send_packet($data)
	{
		$data[] = '';
		fwrite($this->sock, '%xt%'.implode('%', $data).chr(0));
	}
}

class Tasks
{
	var $_p;
}