<?php

require($_SERVER['DOCUMENT_ROOT'] . '/html_dom_parser_src/simple_html_dom.php');
require($_SERVER['DOCUMENT_ROOT'] . '/transl_src/GoogleTranslate.php');

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

	function url_get_contents ($Url) 
	{
    	if (!function_exists('curl_init')) 
    		die('CURL is not installed!');

    	$ch = curl_init();
    	curl_setopt($ch, CURLOPT_URL, $Url);
    	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    	$output = curl_exec($ch);
    	curl_close($ch);
    	return $output;
    }

	function __construct($UrlName, $TargetLang = 'en', $SourceLang = 'ru')
	{
		if ( ! class_exists( 'simple_html_dom_node' ) ) die( 'simple_html_dom_parser was not found' );
		if ( ! class_exists( 'GoogleTranslate' ) ) die( 'GoogleTranslate-class was not found' );
		
		if ( preg_match('/^https?:\/\/.*/', $UrlName) ) 
			$this->html_DOM_code = str_get_html( $this->url_get_contents($UrlName) );
        else 
        	$this->html_DOM_code = str_get_html( file_get_contents($UrlName) );

		$this->lang_in = $SourceLang;
		$this->lang_out = $TargetLang;

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

	private function InitDOMBody() { $this->whole_body_text_arr = $this->html_DOM_code->find('body',0)->find('text'); }

	private function GetTrimmedAndUnEntitiedString($string) { return preg_replace($this->entity_patterns, $this->entity_replacement, trim($string)); }

	private function InitSourceContent()
	{
		for ($raw = 0, $size = count($this->whole_body_text_arr); $raw < $size; $raw++) 
			if (! preg_match( "/^\s*$/", $this->whole_body_text_arr[$raw] ) && preg_match( "/{$this->lang_alph_regex}/u", $this->whole_body_text_arr[$raw] ) )
			{
				$this->source_content_arr[] = $this->whole_body_text_arr[$raw]->plaintext;
				$this->mod_source_content_arr[] = $this->GetTrimmedAndUnEntitiedString($this->whole_body_text_arr[$raw]->plaintext);
			}
	}

	private function GetTranslatedText($text) { return $this->transl_class->translate($this->lang_in, $this->lang_out, $text); }

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

		//translating and merging
		$target_content_string = $this->GetTranslatedText($sep_mod_source_content_string_arr[0]);
		for ($raw = 1, $size = count($sep_mod_source_content_string_arr); $raw < $size; $raw++)
			$target_content_string = $target_content_string . "\n" . $this->GetTranslatedText($sep_mod_source_content_string_arr[$raw]);

		//initializing
		$this->target_content_arr = explode("\n",$target_content_string);
	}

	private function ExchangeDOMContent()
	{
		$target_content_arr_counter = 0;
		for ($raw = 0, $size = count($this->whole_body_text_arr); $raw < $size; $raw++) 
			if (! preg_match( "/^\s*$/", $this->whole_body_text_arr[$raw] ) && preg_match( "/{$this->lang_alph_regex}/u", $this->whole_body_text_arr[$raw] ) )
			{
				$this->whole_body_text_arr[$raw]->innertext = $this->target_content_arr[$target_content_arr_counter];
				$target_content_arr_counter++;
			}
	}

	private function TranslateUnbodyContent()
	{	
		$placeholder_string_source = '';
		$placeholder_string_target_arr = array();
		$placeholder_DOM_code = $this->html_DOM_code->find('*[placeholder],meta[name=description],title');

		foreach ( $placeholder_DOM_code  as $value )
		{
			if (preg_match( "/{$this->lang_alph_regex}/u", $value->{'placeholder'} ) ) 
			{
				$placeholder_string_source = $placeholder_string_source . "\n" . $value->{'placeholder'};
				continue;
			}
			if (preg_match( "/{$this->lang_alph_regex}/u", $value->{'content'} ) )
			{
				$placeholder_string_source = $placeholder_string_source . "\n" . $value->{'content'};
				continue;	
			} 
				
			if (preg_match( "/{$this->lang_alph_regex}/u", $value->innertext ) ) 
				$placeholder_string_source = $placeholder_string_source . "\n" . $value->innertext;
		}

		$placeholder_string_target_arr = explode("\n", $this->GetTranslatedText($placeholder_string_source));

		$placeholder_string_target_arr_counter = 0;
		foreach ( $placeholder_DOM_code  as $value )
		{
			if (preg_match( "/{$this->lang_alph_regex}/u", $value->{'placeholder'} ) )
			{
				$value->{'placeholder'} = $placeholder_string_target_arr[$placeholder_string_target_arr_counter];
				$placeholder_string_target_arr_counter++;
				continue;
			}
			if (preg_match( "/{$this->lang_alph_regex}/u", $value->{'content'} ) )
			{
				$value->{'content'} = $placeholder_string_target_arr[$placeholder_string_target_arr_counter];
				$placeholder_string_target_arr_counter++;
				continue;
			}
			if (preg_match( "/{$this->lang_alph_regex}/u", $value->innertext ) )
			{
				$value->innertext = $placeholder_string_target_arr[$placeholder_string_target_arr_counter];
				$placeholder_string_target_arr_counter++;
			}
		}
	}

	public function Translate()
	{
		if ( $this->html_DOM_code === false ) die( 'Can\'t reach resource' );
		if ( $this->lang_in === $this->lang_out ) die( 'No point to translate if source language and target language are the same language' ); 
		if ( $this->lang_in !== 'en' && $this->lang_in !== 'ru' ) die( 'can\'t translate site from source language you set' );

		$this->InitDOMBody();

		$this->InitSourceContent();
		$this->InitTargetContent();
		$this->ExchangeDOMContent();
		$this->TranslateUnbodyContent();

		return true;
	}

	public function GetTranslatedPage() { return $this->html_DOM_code->save(); }
}

/*Example part
	$Translator = new DefTranslator('http://makena.ru/');
	if ($Translator->Translate() === true) echo $Translator->GetTranslatedPage();
	else echo "Failure :(";*/

?>