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

	/**
	 * Performs raw UDP DNS A and AAAA record lookups for a domain against a
	 * DNS server, populating $this->results with the resolved IPv4/IPv6
	 * addresses. Called when a new dnslookup object is constructed, e.g.
	 * from this plugin's 'dns' type service checks.
	 *
	 * @param string $domain  The domain name to look up.
	 * @param string $dns     The DNS server IP address to query; defaults
	 *                       to '8.8.8.8'.
	 * @param int    $timeout The socket timeout in seconds; defaults to 5.
	 *
	 * @return void
	 */
	function __construct($domain, $dns = '8.8.8.8', $timeout = 5) {
		$this->dns_query($domain, 1, $dns, $timeout, 'A');
		$this->dns_query($domain, 28, $dns, $timeout, 'AAAA');
	}

	/**
	 * Formats the resolved A/AAAA record results as a plain-text listing.
	 * Called from this plugin's DNS test function to build the check's
	 * displayed/searched result data.
	 *
	 * @param string $format Reserved for future output format support;
	 *                       currently only plain text is produced.
	 *                       Defaults to 'text'.
	 *
	 * @return string|false The formatted list of resolved IP addresses, or
	 *                      false if no records were resolved.
	 */
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

	/**
	 * Builds and sends a raw DNS query packet over UDP for a single record
	 * type, then parses the reply into $this->results. Called from the
	 * constructor once for the A record type and once for AAAA.
	 *
	 * @param string $domain  The domain name to query.
	 * @param int    $qtype   The DNS query type code (1 for A, 28 for
	 *                       AAAA).
	 * @param string $dns     The DNS server IP address to query.
	 * @param int    $timeout The socket timeout in seconds.
	 * @param string $type    A label for this query type ('A' or 'AAAA'),
	 *                       used to key the results array.
	 *
	 * @return false|null False if the UDP socket could not be opened;
	 *                    otherwise no return value (results are stored on
	 *                    the instance).
	 */
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

	/**
	 * Encodes a domain name into DNS query label format (length-prefixed
	 * labels terminated by a zero byte). Called from dns_query() to build
	 * the outgoing query packet's question section.
	 *
	 * @param string $domain The domain name to encode.
	 *
	 * @return string The encoded domain name.
	 */
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

	/**
	 * Parses the answer section of a raw DNS reply, extracting resolved
	 * IPv4/IPv6 addresses into $this->results, keyed by record type.
	 * Called from dns_query() after receiving a reply.
	 *
	 * @param string $type_name The record type label ('A' or 'AAAA') this
	 *                         reply corresponds to, used as the results
	 *                         key.
	 * @param int    $reply_len The length of the raw DNS reply buffer.
	 *
	 * @return void
	 */
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

		$ancount = ord($this->dns_reply[7]) * 256 + ord($this->dns_reply[8]);

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
