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
	/** @var string */
	private $dns_reply = '';
	/** @var int */
	private $cIx       = 0;
	/** @var array<string, array<int, string>> */
	private $results   = [];
	private $success   = false;
	private $error     = '';

	/**
	 * Performs raw UDP DNS A and AAAA record lookups for a domain against a
	 * DNS server, populating $this->results with the resolved IPv4/IPv6
	 * addresses. Called when a new dnslookup object is constructed, e.g.
	 * from this plugin's 'dns' type service checks.
	 *
	 * @param string $domain  The domain name to look up.
	 * @param string $dns     The DNS server IP address to query; defaults
	 *                        to '8.8.8.8'.
	 * @param int    $timeout The socket timeout in seconds; defaults to 5.
	 *
	 * @return void
	 */
	function __construct($domain, $dns = '8.8.8.8', $timeout = 5) {
		$this->dns_query($domain, 1, $dns, $timeout, 'A');
		$this->dns_query($domain, 28, $dns, $timeout, 'AAAA');
	}


	public function is_success() {
		return $this->success;
	}

	public function get_error() {
		return $this->error;
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

	/**
	 * Builds and sends a raw DNS query packet over UDP for a single record
	 * type, then parses the reply into $this->results. Called from the
	 * constructor once for the A record type and once for AAAA.
	 *
	 * @param string $domain  The domain name to query.
	 * @param int    $qtype   The DNS query type code (1 for A, 28 for
	 *                        AAAA).
	 * @param string $dns     The DNS server IP address to query.
	 * @param int    $timeout The socket timeout in seconds.
	 * @param string $type    A label for this query type ('A' or 'AAAA'),
	 *                        used to key the results array.
	 *
	 * @return void The query result is stored on the instance; the method
	 *              returns early (without storing anything) if the UDP
	 *              socket could not be opened.
	 */
	private function dns_query($domain, $qtype, $dns, $timeout, $type) {
		$header = chr(0x12) . chr(0x34) . chr(0x01) . chr(0x00) .
			chr(0x00) . chr(0x01) . chr(0x00) . chr(0x00) .
			chr(0x00) . chr(0x00) . chr(0x00) . chr(0x00);

		$packet = $header . $this->dns_name($domain) .
			chr(0x00) . chr($qtype) . chr(0x00) . chr(0x01);

		$socket = @fsockopen("udp://$dns", 53, $errno, $errstr, $timeout);

		if (!$socket) {
			$this->error = 'DNS server did not respond';
			return false;
		}

		fwrite($socket, $packet);
		stream_set_timeout($socket, $timeout);

		$this->dns_reply = fread($socket, 512);

		$socket_status = stream_get_meta_data($socket);

		fclose($socket);

		if (empty($this->dns_reply)) {
			$this->error = 'DNS server returned no response';
			return false;
		}

		if (!empty($socket_status['timed_out'])) {
			$this->error = 'DNS request timed out';
			return false;
		}

		$len = strlen($this->dns_reply);

		if ($len < 12) {
			$this->error = 'Invalid DNS response';
			return false;
		}

		/*
		 * Check response flag.
		 *
		 * Byte 2, bit 7 = QR
		 * It must be set for a DNS response.
		 */
		if (!(ord($this->dns_reply[2]) & 0x80)) {
			$this->error = 'Invalid DNS response';
			return false;
		}

		/*
		 * RCODE is the last 4 bits of byte 3.
		 */
		$rcode = ord($this->dns_reply[3]) & 0x0f;

		if ($rcode != 0) {
			switch ($rcode) {
				case 1:
					$this->error = 'DNS server returned FORMERR';
					break;

				case 2:
					$this->error = 'DNS server returned SERVFAIL';
					break;

				case 3:
					$this->error = 'DNS name does not exist';
					break;

				case 4:
					$this->error = 'DNS server does not support this query';
					break;

				case 5:
					$this->error = 'DNS server refused the query';
					break;

				default:
					$this->error = 'DNS server returned an error';
					break;
			}

			return false;
		}

		/*
		 * A valid DNS response was received.
		 */
		$this->success = true;
		$this->cIx = 12;
		$this->parse_response($type, $len);

		return true;
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

			if ($len > 63) {
				return '';
			}

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
	 *                          reply corresponds to, used as the results
	 *                          key.
	 * @param int    $reply_len The length of the raw DNS reply buffer.
	 *
	 * @return void
	 */
	private function parse_response($type_name, $reply_len) {
		/*
		 * Skip question section.
		 */
		while ($this->cIx < $reply_len) {
			$length = ord($this->dns_reply[$this->cIx]);
			$this->cIx++;

			if ($length == 0) {
				break;
			}

			/*
			 * Compression pointer in question name.
			 */
			if (($length & 0xc0) == 0xc0) {
				if ($this->cIx >= $reply_len) {
					return;
				}

				$this->cIx++;
				break;
			}

			$this->cIx += $length;

			if ($this->cIx > $reply_len) {
				return;
			}
		}

		/*
		 * Skip QTYPE + QCLASS.
		 */
		if ($this->cIx + 4 > $reply_len) {
			return;
		}

		$this->cIx += 4;

		/*
		 * ANCOUNT is bytes 6-7 of the DNS header.
		 */
		$ancount = ord($this->dns_reply[6]) * 256 + ord($this->dns_reply[7]);

		for ($i = 0; $i < min($ancount, 10); $i++) {
			if ($this->cIx + 12 > $reply_len) {
				return;
			}

			/*
			 * Skip NAME.
			 *
			 * Most DNS responses use a compression pointer
			 * here (2 bytes).
			 */
			if ((ord($this->dns_reply[$this->cIx]) & 0xc0) == 0xc0) {
				$this->cIx += 2;
			} else {
				/*
				 * Non-compressed NAME.
				 */
				while ($this->cIx < $reply_len) {
					$length = ord($this->dns_reply[$this->cIx]);
					$this->cIx++;

					if ($length == 0) {
						break;
					}

					if (($length & 0xc0) == 0xc0) {
						if ($this->cIx >= $reply_len) {
							return;
						}

						$this->cIx++;
						break;
					}

					$this->cIx += $length;

					if ($this->cIx > $reply_len) {
						return;
					}
				}
			}

			if ($this->cIx + 10 > $reply_len) {
				return;
			}

			/*
			 * TYPE
			 */
			$type = ord($this->dns_reply[$this->cIx]) * 256 + ord($this->dns_reply[$this->cIx + 1]);

			$this->cIx += 2;

			/*
			 * CLASS + TTL
			 */
			$this->cIx += 6;

			/*
			 * RDLENGTH
			 */
			$rdlen = ord($this->dns_reply[$this->cIx]) * 256 + ord($this->dns_reply[$this->cIx + 1]);

			$this->cIx += 2;

			if ($this->cIx + $rdlen > $reply_len) {
				return;
			}

			if ($type == 1 && $rdlen == 4) {
				/*
				 * IPv4
				 */
				$ip = ord($this->dns_reply[$this->cIx]) . '.' .
					ord($this->dns_reply[$this->cIx + 1]) . '.' .
					ord($this->dns_reply[$this->cIx + 2]) . '.' .
					ord($this->dns_reply[$this->cIx + 3]);

				$this->results['A'][] = $ip;
			} elseif ($type == 28 && $rdlen == 16) {
				/*
				 * IPv6
				 */
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
