<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
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

class dnslookup {
	private $dns_reply = '';
	private $cIx       = 0;
	private $results   = [];

	function __construct($domain, $dns = '8.8.8.8', $timeout = 5) {
		$this->dns_query($domain, 1, $dns, $timeout, 'A');
		$this->dns_query($domain, 28, $dns, $timeout, 'AAAA');
	}

	public function get_results($format = 'text') {
		$output = '';

		if (empty($this->results)) {
			return false;
		}

		foreach ($this->results as $type => $ips) {
			foreach ($ips as $ip) {
				$output .= "  $ip\n";
			}
			$output .= "\n";
		}

		return $output;
	}

	private function dns_query($domain, $qtype, $dns, $timeout, $type) {
		$header = chr(0x12) . chr(0x34) . chr(0x01) . chr(0x00) . chr(0x00) . chr(0x01) .
			chr(0x00) . chr(0x00) . chr(0x00) . chr(0x00) . chr(0x00) . chr(0x00);

		$packet = $header . $this->dns_name($domain) .
			chr(0x00) . chr($qtype) . chr(0x00) . chr(0x01);

		$socket = @fsockopen("udp://$dns", 53, $errno, $errstr, $timeout);

		if (!$socket) {
			return false;
		}

		fwrite($socket, $packet);
		stream_set_timeout($socket, $timeout);
		$this->dns_reply = fread($socket, 512);
		fclose($socket);

		$len = strlen($this->dns_reply);

		if ($len > 12) {
			$this->cIx = 12;
			$this->parse_response($type, $len);
		}
	}

	private function dns_name($domain) {
		$parts = explode('.', $domain);
		$name  = '';

		foreach ($parts as $part) {
			$len = strlen($part);
			$name .= chr($len);
			$name .= $part;
		}

		return $name . chr(0);
	}

	private function parse_response($type_name, $reply_len) {
		// Skip question
		$max_skip = min(255, $reply_len - $this->cIx);

		for ($i = 0; $i < $max_skip; $i++) {
			if (ord($this->dns_reply[$this->cIx]) == 0) {
				$this->cIx++;

				break;
			}
			$this->cIx++;
		}
		$this->cIx += 4;

		$ancount = ord($this->dns_reply[6]) * 256 + ord($this->dns_reply[7]);

		for ($i = 0; $i < min($ancount, 10); $i++) {
			if ($this->cIx + 12 > $reply_len) {
				break;
			}

			$this->cIx += 2; // name pointer
			$type = ord($this->dns_reply[$this->cIx]) * 256 + ord($this->dns_reply[$this->cIx + 1]);
			$this->cIx += 8;  // CLASS + TTL
			$rdlen = ord($this->dns_reply[$this->cIx]) * 256 + ord($this->dns_reply[$this->cIx + 1]);
			$this->cIx += 2;

			if ($this->cIx + $rdlen > $reply_len) {
				break;
			}

			if ($type == 1 && $rdlen == 4) {
				// IPv4
				$ip = ord($this->dns_reply[$this->cIx]) . '.' .
					ord($this->dns_reply[$this->cIx + 1]) . '.' .
					ord($this->dns_reply[$this->cIx + 2]) . '.' .
					ord($this->dns_reply[$this->cIx + 3]);
				$this->results['A'][] = $ip;
			} elseif ($type == 28 && $rdlen == 16) {
				// IPv6
				$ipv6 = '';

				for ($j = 0; $j < 16; $j += 2) {
					$byte1 = ord($this->dns_reply[$this->cIx + $j]);
					$byte2 = ord($this->dns_reply[$this->cIx + $j + 1]);
					$ipv6 .= sprintf('%02x%02x', $byte1, $byte2);

					if ($j < 14) {
						$ipv6 .= ':';
					}
				}
				$this->results['AAAA'][] = $ipv6;
			}
			$this->cIx += $rdlen;
		}
	}
}
