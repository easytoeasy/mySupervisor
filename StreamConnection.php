<?php


class StreamConnection
{
	protected $socket;
	protected $timeout = 2; //s
	protected $client;

	public function __construct($host)
	{
		$this->socket = $this->connect($host);
	}

	public function connect($host)
	{
		$socket = stream_socket_server($host, $errno, $errstr);
		if (!$socket) {
			exit('stream error');
		}
		stream_set_timeout($socket, $this->timeout);
		stream_set_chunk_size($socket, 1024);
		stream_set_blocking($socket, false);
		$this->client = [$socket];
		return $socket;
	}

	public function accept(Closure $callback)
	{
		$read = $this->client;
		if (stream_select($read, $write, $except, 1) < 1) return;
		if (in_array($this->socket, $read)) {
			$cs = stream_socket_accept($this->socket);
			$this->client[] = $cs;
		}
		foreach ($read as $s) {
			if ($s == $this->socket) continue;
			$header = fread($s, 1024);
			if (empty($header)) {
				$index = array_search($s, $this->client);
				if ($index)
					unset($this->client[$index]);
				$this->close($s);
				continue;
			}
			Http::parse_http($header);
			$uniqid = isset($_GET['uniqid']) ? $_GET['uniqid'] : '';
			$action = isset($_GET['action']) ? $_GET['action'] : '';
			$response = $callback($uniqid, $action);
			$this->write($s, $response);
			$index = array_search($s, $this->client);
			if ($index)
				unset($this->client[$index]);
			$this->close($s);
		}
	}

	public function write($socket, $response)
	{
		$ret = fwrite($socket, $response, strlen($response));
	}

	public function close($socket)
	{
		$flag = fclose($socket);
	}

	/**
	 * Get the value of socket
	 */
	public function getSocket()
	{
		return $this->socket;
	}
}
