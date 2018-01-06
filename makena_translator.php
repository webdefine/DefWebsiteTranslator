<?php

require($_SERVER[DOCUMENT_ROOT] . '/html_dom_parser_src/simple_html_dom.php');

/**
* This is a class which is created for translating websites (special for "MAKENA")
*/
class DefTranslator 
{
	private $html_DOM_code;

	function __construct($UrlName)
	{
		$this->html_DOM_code = file_get_html($UrlName);
	}
}

//Example part
$Translator = new DefTranslator('http://makena.ru/');

?>