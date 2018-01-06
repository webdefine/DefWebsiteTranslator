<?php

require($_SERVER[DOCUMENT_ROOT] . '/html_dom_parser_src/simple_html_dom.php');

/**
* This is a class which is created for translating websites (special for "MAKENA")
*/
class DefTranslator 
{
	private $html_DOM_code;
	private $html_DOM_code_finish;
	private $lang_in;
	private $lang_out;

	function __construct($UrlName, $TargetLang = 'en', $SourceLang = 'ru')
	{
		$this->html_DOM_code = file_get_html($UrlName);
		$this->lang_in = $SourceLang;
		$this->lang_out = $TargetLang;
	}

	public function Translate()
	{
		if ($this->lang_in === $this->lang_out || $this->html_DOM_code === false ) return false;
		//code
		return true;
	}

	public function GetTranslatedPage()
	{
		return $this->html_DOM_code_finish->save();
	}
}

//Example part
$Translator = new DefTranslator('http://makena.ru/');
echo $Translator->Translate() ? 'true' : 'false';

?>