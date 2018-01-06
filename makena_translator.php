<?php

require($_SERVER[DOCUMENT_ROOT] . '/html_dom_parser_src/simple_html_dom.php');

/**
* This is a class which is created for translating websites (special for "MAKENA")
*/
class DefTranslator 
{
	private $html_DOM_code;

	private $lang_in;
	private $lang_out;

	private $whole_body_text_arr = array();
	private $source_content_arr = array();
	private $mod_source_content_arr = array();

	private $entity_patterns = array('/&#8230;/','/&ndash;/','/&nbsp;/','/&(r|l)aquo;/');
	private $entity_replacement = array('...',' - ',' ','"');

	function __construct($UrlName, $TargetLang = 'en', $SourceLang = 'ru')
	{
		$this->html_DOM_code = file_get_html($UrlName);
		$this->lang_in = $SourceLang;
		$this->lang_out = $TargetLang;

		$this->whole_body_text_arr = $this->html_DOM_code->find('body',0)->find('text');	
	}

	private function GetTrimmedAndUnEntitiedString($string)
	{
		return preg_replace($this->entity_patterns, $this->entity_replacement, trim($string));
	}

	private function InitSourceContent()
	{
		for ($raw = 0, $size = count($this->whole_body_text_arr); $raw < $size; $raw++) 
			if (! preg_match( "/^\s*$/", $this->whole_body_text_arr[$raw] ) && preg_match( "/[а-яА-ЯёЁ]/u", $this->whole_body_text_arr[$raw] ) )
			{
				$this->source_content_arr[] = $this->whole_body_text_arr[$raw]->plaintext;
				$this->mod_source_content_arr[] = $this->GetTrimmedAndUnEntitiedString($this->whole_body_text_arr[$raw]->plaintext);
			}
	}

	private function DeleteComments()
	{
		foreach ( $this->html_DOM_code->find('comment') as $e ) $e->outertext = '';
	}

	public function Translate()
	{
		if ( $this->lang_in === $this->lang_out || $this->html_DOM_code === false ) return false;
		$this->DeleteComments();
		$this->InitSourceContent();
		//code
		return true;
	}

	public function GetTranslatedPage()
	{
		return $this->html_DOM_code->save();
	}
}

//Example part
$Translator = new DefTranslator('http://makena.ru/');
echo $Translator->Translate() ? 'true' : 'false';

?>