<?php

require($_SERVER[DOCUMENT_ROOT] . '/html_dom_parser_src/simple_html_dom.php');
require($_SERVER[DOCUMENT_ROOT] . '/transl_src/vendor/autoload.php');

use \Statickidz\GoogleTranslate;

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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
	
	private $target_content_arr = array();

	private $entity_patterns = array('/&#8230;/','/&ndash;/','/&nbsp;/','/&(r|l)aquo;/');
	private $entity_replacement = array('...',' - ',' ','"');

	private $lang_alph_regex;

	private $transl_class;

	function __construct($UrlName, $TargetLang = 'en', $SourceLang = 'ru')
	{
		$this->html_DOM_code = file_get_html($UrlName);
		$this->lang_in = $SourceLang;
		$this->lang_out = $TargetLang;

		$this->whole_body_text_arr = $this->html_DOM_code->find('body',0)->find('text');

		$this->transl_class = new GoogleTranslate();

		switch ($this->lang_in) 
		{
			case 'ru':
				$this->lang_alph_regex = '[а-яА-ЯёЁ]';
				break;
			
			case 'en':
				$this->lang_alph_regex = '[a-zA-Z]';
				break;

			default:
				$this->lang_alph_regex = '[а-яА-ЯёЁ]';
				break;
		}
	}

	private function DeleteComments()
	{
		foreach ( $this->html_DOM_code->find('comment') as $e ) $e->outertext = '';
	}

	private function GetTrimmedAndUnEntitiedString($string)
	{
		return preg_replace($this->entity_patterns, $this->entity_replacement, trim($string));
	}

	private function InitSourceContent()
	{
		for ($raw = 0, $size = count($this->whole_body_text_arr); $raw < $size; $raw++) 
			if (! preg_match( "/^\s*$/", $this->whole_body_text_arr[$raw] ) && preg_match( "/{$this->lang_alph_regex}/u", $this->whole_body_text_arr[$raw] ) )
			{
				$this->source_content_arr[] = $this->whole_body_text_arr[$raw]->plaintext;
				$this->mod_source_content_arr[] = $this->GetTrimmedAndUnEntitiedString($this->whole_body_text_arr[$raw]->plaintext);
			}
	}

	private function GetTranslatedText($text)
	{
		return $this->transl_class->translate($this->lang_in, $this->lang_out, $text);
	}

	private function InitTargetContent()
	{
		$mod_source_content_string = implode("\n", $this->mod_source_content_arr);

		if (strlen($mod_source_content_string) <= 8000) 
		{
			$this->target_content_arr = explode("\n",$this->GetTranslatedText($mod_source_content_string));
			return;
		}	

		//separation
		$sep_mod_source_content_string_arr = array();
		while (strlen($mod_source_content_string) > 8000)
		{
			$sep_mod_source_content_string_arr[] = substr( $mod_source_content_string, 0, strpos($mod_source_content_string, "\n", 7500) + 1);
			$mod_source_content_string = substr( $mod_source_content_string, strpos($mod_source_content_string, "\n", 7500));
		}
			$sep_mod_source_content_string_arr[] = $mod_source_content_string;

		//merging
		$target_content_string = '';
		for ($raw = 0, $size = count($sep_mod_source_content_string_arr); $raw < $size; $raw++)
			$target_content_string = $target_content_string . "\n" . $this->GetTranslatedText($sep_mod_source_content_string_arr[$raw]);

		//translating and initializing
		$this->target_content_arr = explode("\n",$target_content_string);
		array_shift($this->target_content_arr);
	}

	private function ExchngeDOMContent()
	{
		$target_content_arr_counter = 0;
		for ($raw = 0, $size = count($this->whole_body_text_arr); $raw < $size; $raw++) 
			if (! preg_match( "/^\s*$/", $this->whole_body_text_arr[$raw] ) && preg_match( "/{$this->lang_alph_regex}/u", $this->whole_body_text_arr[$raw] ) )
			{
				$this->whole_body_text_arr[$raw]->innertext = $this->target_content_arr[$target_content_arr_counter];
				$target_content_arr_counter++;
			}
	}

	private function TranslatePlaceholders()
	{	
		$placeholder_string_source = '';
		$placeholder_string_target_arr = array();
		$placeholder_DOM_code = $this->html_DOM_code->find('*[placeholder]');

		foreach ( $placeholder_DOM_code  as $value )
			if (preg_match( "/{$this->lang_alph_regex}/u", $value->{'placeholder'} ) ) 
				$placeholder_string_source = $placeholder_string_source . "\n" . $value->{'placeholder'};

		$placeholder_string_target_arr = explode("\n", $this->GetTranslatedText($placeholder_string_source));

		$placeholder_string_target_arr_counter = 0;
		foreach ( $placeholder_DOM_code  as $value )
			if (preg_match( "/{$this->lang_alph_regex}/u", $value->{'placeholder'} ) )
			{
				$value->{'placeholder'} = $placeholder_string_target_arr[$placeholder_string_target_arr_counter];
				$placeholder_string_target_arr_counter++;
			}
	}

	public function Translate()
	{
		if ( $this->lang_in === $this->lang_out || $this->html_DOM_code === false || ( $this->lang_in !== 'en' && $this->lang_in !== 'ru')) return false;
		$this->DeleteComments();
		$this->InitSourceContent();
		$this->InitTargetContent();
		$this->ExchngeDOMContent();
		$this->TranslatePlaceholders();
		return true;
	}

	public function GetTranslatedPage()
	{
		return $this->html_DOM_code->save();
	}
}

/*Example part
	$Translator = new DefTranslator('http://makena.ru/');
	if ($Translator->Translate() === true) echo $Translator->GetTranslatedPage();
	else echo "Failure :(";*/

?>