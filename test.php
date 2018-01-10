<?php

require ( 'makena_translator.php' );

//This is an example
	$Translator = new DefTranslator('http://makena.ru/');
	if ($Translator->Translate() === true) echo $Translator->GetTranslatedPage();
	else echo "Failure :(";
?>