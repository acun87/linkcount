<?php

class Title {
	public const REDIS_DB_VER = 3;

	private $databaseName;
	private $projectURL;
	private $title;
	private $namespaceInfo;

	public function __construct($text, $databaseName, $projectURL) {
		$this->databaseName = $databaseName;
		$this->projectURL = $projectURL;

		$text = strtr($text, '_', ' ');
		$text = trim($text);

		if ($text[0] == ':') {
			$text = substr($text, 1);
		}

		list($maybeNamespace, $title) = $this->breakupText($text);

		$namespaceInfo = $this->getNamespaceInfo($maybeNamespace);

		if (!$namespaceInfo) {
			$namespaceInfo = $this->getNamespaceInfo('');
			$title = $text;
		}

		$this->title = $namespaceInfo[2] ? ucfirst($title) : $title;
		$this->namespaceInfo = $namespaceInfo;
	}

	public function getNamespaceId() {
		return $this->namespaceInfo[0];
	}

	public function getDBKey() {
		return strtr($this->title, ' ', '_');
	}

	public function getFullText() {
		if ($this->namespaceInfo[1] == '') {
			return $this->title;
		}

		return "{$this->namespaceInfo[1]}:{$this->title}";
	}

	private function breakupText($text) {
		if (strpos($text, ':') === false) {
			return ['', $text];
		} else {
			return explode(':', $text, 2);
		}
	}

	private function getNamespaceInfo($namespace) {
		$redis = new Redis;

		$redis->connect(Config::get('redis-server'), Config::get('redis-port'));
		$redis->auth(Config::get('redis-auth'));

		$redis->close();

		$prefix = Config::get('redis-prefix');
		$ver = 'v' . self::REDIS_DB_VER;
		$nsInfoHashKey = "$prefix:$ver:{$this->databaseName}";

		if (!$redis->exists($nsInfoHashKey)) {
			$namespaceByName = $this->getNamespaceInfoStrings();

			$redis->hMSet($nsInfoHashKey, $namespaceByName);
			$redis->expire($nsInfoHashKey, 86400);
		}

		$namespaceInfoString = $redis->hGet($nsInfoHashKey, strtolower($namespace));

		if (!$namespaceInfoString) {
			return null;
		}

		return json_decode($namespaceInfoString);
	}

	private function getNamespaceInfoStrings() {
		$curl = curl_init();

		curl_setopt_array($curl, [
			CURLOPT_URL => $this->projectURL . '/w/api.php?action=query&meta=siteinfo&siprop=namespaces|namespacealiases&format=json&formatversion=2',
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_USERAGENT => Config::get('useragent')
		]);

		$info = json_decode(curl_exec($curl));

		curl_close($curl);

		$namespaceByName = [];
		$namespaceById = [];

		foreach ($info->query->namespaces as $namespace) {
			$nsInfoString = json_encode([
				$namespace->id,
				$namespace->name,
				$namespace->case == 'first-letter',
			]);

			$namespaceByName[strtolower($namespace->name)] = $nsInfoString;

			if (isset($namespace->canonical)) {
				$namespaceByName[strtolower($namespace->canonical)] = $nsInfoString;
			}

			$namespaceById[$namespace->id] = $nsInfoString;
		}

		foreach ($info->query->namespacealiases as $namespace) {
			$namespaceByName[strtolower($namespace->alias)] = $namespaceById[$namespace->id];
		}

		return $namespaceByName;
	}
}
