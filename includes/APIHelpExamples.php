<?php


class APIHelpExamples implements ProducesHtml {
	private $examples;
	private $prefix;

	public function __construct(...$examples) {
		$this->examples = $examples;
		$this->prefix = (!empty($_SERVER['HTTPS']) ? 'https' : 'http') . "://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}";
	}

	public function getHtml() {
		$out = '';

		foreach ($this->examples as $example) {
			$url = $this->prefix . '?' . $example;
			$out .= "<li><a href=\"$url\">$url</a></li>";
		}

		return "<ul>$out</ul>";
	}
}
