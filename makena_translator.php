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

	private $entity_patterns = array('/&#8230;/','/&ndash;/','/&nbsp;/','/&(r|l)aquo;/');
	private $entity_replacement = array('...',' - ',' ','"');

	private $lang_alph_regex;

	private $transl_class;

	function url_get_contents ($Url) 
	{
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
		
		if ( preg_match('/^https?:\/\/.*/', $UrlName) )
			{
				if ( ! function_exists('curl_init') ) die( 'CURL is not installed!' );
				$this->html_DOM_code = str_get_html( $this->url_get_contents($UrlName) );
			}
        else 
        	$this->html_DOM_code = str_get_html( file_get_contents($UrlName) );

		$this->lang_in = $SourceLang;
		$this->lang_out = $TargetLang;

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

	private function DOM_get_body_text($DOM_webpage) { return $DOM_webpage->find('body',0)->find('text'); }

	private function string_modificate($string) { return preg_replace($this->entity_patterns, $this->entity_replacement, trim($string)); }

	private function array_modificate($DOM_page_body_text)
	{
		$fixed_arr = array();
		for ($raw = 0, $size = count($DOM_page_body_text); $raw < $size; $raw++) 
			if ( preg_match( "/(?!=^\s*$){$this->lang_alph_regex}/", $DOM_page_body_text[$raw] ) )
				$fixed_arr[] = $this->string_modificate($DOM_page_body_text[$raw]->plaintext);

		return $fixed_arr;
	}

	private function GetTranslatedText($text) { return $this->transl_class->translate($this->lang_in, $this->lang_out, $text); }

	private function array_get_trans($source_arr)
	{
		$mod_source_content_string = implode("\n", $source_arr);

		if (mb_strlen($mod_source_content_string) <= 5000) 
			return explode("\n",$this->GetTranslatedText($mod_source_content_string));

		//separation
		$sep_mod_source_content_string_arr = array();
		while (mb_strlen($mod_source_content_string) > 5000)
		{
			$sep_mod_source_content_string_arr[] = substr( $mod_source_content_string, 0, strpos($mod_source_content_string, "\n", 5000) + 1);
			$mod_source_content_string = substr( $mod_source_content_string, strpos($mod_source_content_string, "\n", 5000) );
		}
			$sep_mod_source_content_string_arr[] = $mod_source_content_string;

		//translating and merging
		$target_content_string = $this->GetTranslatedText($sep_mod_source_content_string_arr[0]);
		for ($raw = 1, $size = count($sep_mod_source_content_string_arr); $raw < $size; $raw++)
			$target_content_string .= "\n" . $this->GetTranslatedText($sep_mod_source_content_string_arr[$raw]);

		//initializing
		return explode("\n",$target_content_string);
	}

	private function TranslateUnbodyContent()
	{	
		$outbody_string_source = '';
		$outbody_string_target_arr = array();
		$outbody_DOM_code = $this->html_DOM_code->find('*[placeholder],meta[name=description],title');

		foreach ( $outbody_DOM_code  as $value )
		{
			if (preg_match( "/{$this->lang_alph_regex}/", $value->{'placeholder'} ) ) 
			{
				$outbody_string_source .= "\n" . $value->{'placeholder'};
				continue;
			}
			if (preg_match( "/{$this->lang_alph_regex}/", $value->{'content'} ) )
			{
				$outbody_string_source .= "\n" . $value->{'content'};
				continue;	
			} 
				
			if (preg_match( "/{$this->lang_alph_regex}/", $value->innertext ) ) 
				$outbody_string_source .= "\n" . $value->innertext;
		}

		$outbody_string_target_arr = explode("\n", $this->GetTranslatedText($outbody_string_source));

		$outbody_string_target_arr_counter = 0;
		foreach ( $outbody_DOM_code  as $value )
		{
			if (preg_match( "/{$this->lang_alph_regex}/", $value->{'placeholder'} ) )
			{
				$value->{'placeholder'} = $outbody_string_target_arr[$outbody_string_target_arr_counter];
				$outbody_string_target_arr_counter++;
				continue;
			}
			if (preg_match( "/{$this->lang_alph_regex}/", $value->{'content'} ) )
			{
				$value->{'content'} = $outbody_string_target_arr[$outbody_string_target_arr_counter];
				$outbody_string_target_arr_counter++;
				continue;
			}
			if (preg_match( "/{$this->lang_alph_regex}/", $value->innertext ) )
			{
				$value->innertext = $outbody_string_target_arr[$outbody_string_target_arr_counter];
				$outbody_string_target_arr_counter++;
			}
		}
	}

	private function DOM_get_changed_content($DOM_body_text,$translated_array)
	{
		for ($source_raw = 0, $target_raw = 0, $s_1 = count($DOM_body_text), $s_2 = count($translated_array); 
			$source_raw < $s_1 && $target_raw < $s_2;
			$source_raw++)
			if ( preg_match( "/(?!=^\s*$){$this->lang_alph_regex}/", $DOM_body_text[$source_raw] ) )
			{
				$DOM_body_text[$source_raw]->innertext = $translated_array[$target_raw];
				$target_raw++;
			}

		return $DOM_body_text;
	}

	public function Translate()
	{
		if ( ! class_exists( 'GoogleTranslate' ) ) die( 'GoogleTranslate-class was not found' );
		if ( $this->html_DOM_code === false ) die( 'Can\'t reach resource' );
		if ( $this->lang_in === $this->lang_out ) die( 'No point to translate if source language and target language are the same language' ); 
		if ( $this->lang_in !== 'en' && $this->lang_in !== 'ru' ) die( 'can\'t translate site from source language you set' );

		$this->transl_class = new GoogleTranslate();

		$DOM_body_text = $this->DOM_get_body_text( $this->html_DOM_code );
		$this->whole_body_text_arr = $this->DOM_get_changed_content( $DOM_body_text, $this->array_get_trans( $this->array_modificate( $DOM_body_text ) ) );

		$this->TranslateUnbodyContent();

		return true;
	}

	public function GetTranslatedPage() { return $this->html_DOM_code->save(); }
}

?>