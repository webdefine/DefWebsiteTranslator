<?php

/**
* This is a class which is created for translating websites (special for "MAKENA")
*/
class DefTranslator 
{
	private $html_DOM_code;

	function __construct($UrlName)
	{
		$html_DOM_code = file_get_html($UrlName);
	}
}

?>