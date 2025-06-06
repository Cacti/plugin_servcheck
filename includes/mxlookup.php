<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2025 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

class mxlookup {
	var $dns_socket = NULL;
	var $QNAME      = '';
	var $dns_packet = NULL;
	var $ANCOUNT    = 0;
	var $cIx        = 0;
	var $arrMX      = array();
	var $dns_repl_domain;
	var $dns_reply  = '';
	var $err_code = '';
	var $err_mess = '';

	function __construct($domain, $dns = '4.2.2.1', $timeout = 5) {
		$this->QNAME($domain);
		$this->pack_dns_packet();

		$dns_socket = fsockopen("udp://$dns", 53, $err_code, $err_mess, $timeout);

		fwrite($dns_socket, $this->dns_packet, strlen($this->dns_packet));
		stream_set_timeout($dns_socket, $timeout);

		$this->dns_reply  = fread($dns_socket,1);

		if ($this->dns_reply) { // server replied
			$bytes            = stream_get_meta_data($dns_socket);
			$this->dns_reply .= fread($dns_socket,$bytes['unread_bytes']);

			fclose($dns_socket);
		}

		$this->cIx       = 6;
		$this->ANCOUNT   = $this->gord(2);
		$this->cIx      += 4;

		$this->parse_data($this->dns_repl_domain);

		$this->cIx      += 7;

		for ($ic = 1; $ic <= $this->ANCOUNT; $ic++) {
			$QTYPE = ord($this->gdi($this->cIx));
			if ($QTYPE !== 1) {
				print('[Record not returned]');
				die();
			}

			$this->cIx += 8;

			$ip = ord($this->gdi($this->cIx)) . '.' . ord($this->gdi($this->cIx)) . '.' . ord($this->gdi($this->cIx)) . '.' . ord($this->gdi($this->cIx));
			$this->arrMX[] = $ip;

			//$mxPref = ord($this->gdi($this->cIx));
			//$this->parse_data($curmx);
			//$this->arrMX[] = array('MX_Pref' => $mxPref, 'MX' => $curmx);
			//$this->cIx += 3;
		}
	}

	function __destruct() {
		return true;
	}

	function parse_data(&$retval) {
		$arName = array();
		$byte   = ord($this->gdi($this->cIx));

		while($byte !== 0) {
			if ($byte == 192) { //compressed
				$tmpIx = $this->cIx;
				$this->cIx = ord($this->gdi($cIx));
				$tmpName = $retval;
				$this->parse_data($tmpName);
				$retval=$retval . '.' . $tmpName;
				$this->cIx = $tmpIx+1;
				return;
			}

			$retval='';
			$bCount = $byte;

			for($b=0;$b<$bCount;$b++) {
				$retval .= $this->gdi($this->cIx);
			}

			$arName[] = $retval;
			$byte     = ord($this->gdi($this->cIx));
		}

		$retval = join('.',$arName);
	}

	function gdi(&$cIx,$bytes=1) {
		$this->cIx++;

		return(substr($this->dns_reply, $this->cIx-1, $bytes));
	}

	function QNAME($domain) {
		$dot_pos = 0;
		$temp    = '';

		while($dot_pos = strpos($domain, '.')) {
			$temp         = substr($domain, 0, $dot_pos);
			$domain       = substr($domain, $dot_pos + 1);
			$this->QNAME .= chr(strlen($temp)) . $temp;
		}

		$this->QNAME .= chr(strlen($domain)) . $domain.chr(0);
	}

	function gord($ln = 1) {
		$reply = '';

		for($i = 0; $i < $ln; $i++) {
			$reply .= ord(substr($this->dns_reply, $this->cIx, 1));
			$this->cIx++;
		}

		return $reply;
	}

	function pack_dns_packet() {
		$this->dns_packet =
			chr(0).chr(1).
			chr(1).chr(0).
			chr(0).chr(1).
			chr(0).chr(0).
			chr(0).chr(0).
			chr(0).chr(0).
			$this->QNAME .
			chr(0).chr(1).
			chr(0).chr(1);
	}
}
