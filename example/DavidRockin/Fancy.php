<?php

namespace DavidRockin;

/**
 * An example Poydia listener
 *
 * This is an example listener/plugin, which will override
 * previously called listeners. This example listener enhances
 * the display of posts
 *
 * @author		David Tkachuk
 * @package		Poydia-Example
 * @subpackage	Poydia
 * @version		0.1
 */
class Fancy implements \Podiya\Listener {

	private $podiya;

	public function registerEvents(\Podiya\Podiya $podiya) {
		$this->podiya = $podiya;
		$podiya->registerEvent("create_post", [$this, "fancyPost"]);
	}
	
	public function fancyPost($username, $group, $message, $date) {
		$result = "<div style=\"padding: 9px 16px;border:1px solid #DADADA;margin-bottom:16px;background:#F1F1F1;font-family:Arial;font-size:15px;\">" .
			"<strong>Posted by</strong> " . $this->podiya->callEvent("format_username", $username) . 
				" (" . $this->podiya->callEvent("format_group", $group) . ")<br />" .
			"<strong>Posted Date</strong> " . $this->podiya->callEvent("format_date", $date) . "<br />" .
				$this->podiya->callEvent("format_message", $message);
		
		return $result . "</div>";
	}

}
