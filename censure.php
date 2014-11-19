<?php

/**
 * Функция пытается определить наличие мата (нецензурных, матерных слов) в html-тексте в кодировке UTF-8.
 *
 * Алгоритм достаточно надёжен и быстр, в т.ч. на больших объёмах данных.
 * Метод обнаружения мата основывается на корнях и предлогах русского языка, а не на словаре.
 * Слова "лох", "хер", "залупа", "сука" матерными словами не считаются (см. словарь Даля)
 *
 * http://www.google.com/search?q=%F2%EE%EB%EA%EE%E2%FB%E9%20%F1%EB%EE%E2%E0%F0%FC%20%F0%F3%F1%F1%EA%EE%E3%EE%20%EC%E0%F2%E0&ie=cp1251&oe=UTF-8
 * http://www.awd.ru/dic.htm (Толковый словарь русского мата.)
 *
 * @param	string			   $s		 строка в кодировке UTF-8
 * @param	string			   $delta	 ширина найденного фрагмента в словах
 *										   (кол-во слов от матного слова слева и справа, максимально 10)
 * @param	string			   $continue  строка, которая будет вставлена в начале в конце фрагмента
 * @return   mixed(false/string/int)		 Возвращает FALSE, если мат не обнаружен, иначе фрагмент текста с матерным словом.
 *										   В случае возникновения ошибки возвращает код ошибки > 0:
 *										   * PREG_INTERNAL_ERROR
 *										   * PREG_BACKTRACK_LIMIT_ERROR (see also pcre.backtrack_limit)
 *										   * PREG_RECURSION_LIMIT_ERROR (see also pcre.recursion_limit)
 *										   * PREG_BAD_UTF8_ERROR
 * @dependencies strip_tags_smart(), utf8_html_entity_decode(), utf8_convert_case()
 * @since	2005
 * @license  http://creativecommons.org/licenses/by-sa/3.0/
 * @author   Nasibullin Rinat <nasibullin at starlink ru>
 * @charset  UTF-8
 * @version  3.1.5
 */
function censure($s, $delta = 3, $continue = "\xe2\x80\xa6")
{
	#предлоги русского языка:
	#[всуо]|
	#по|за|на|об|до|от|вы|вс|вз|из|ис|
	#под|про|при|над|низ|раз|рас|воз|вос|
	#пооб|повы|пона|поза|недо|пере|одно|
	#полуза|произ|пораз|много|
	static $pretext = array(
		#1
		'[уyоo]_?		(?=[еёeхx])',		#у, о   (уебать, охуеть)
		'[вvbсc]_?	   (?=[хпбмгжxpmgj])',  #в, с   (впиздячить, схуярить)
		'[вvbсc]_?[ъь]_? (?=[еёe])',		  #въ, съ (съебаться, въебать)
		'ё_?			 (?=[бb])',		   #ё	  (ёбля)
		#2
		'[вvb]_?[ыi]_?',	  #вы
		'[зz3]_?[аa]_?',	  #за
		'[нnh]_?[аaеeиi]_?',  #на, не, ни
		'[вvb]_?[сc]_?		  (?=[хпбмгжxpmgj])',  #вс (вспизднуть)
		'[оo]_?[тtбb]_?		 (?=[хпбмгжxpmgj])',  #от, об
		'[оo]_?[тtбb]_?[ъь]_?   (?=[еёe])',		  #отъ, объ
		'[иiвvb]_?[зz3]_?	   (?=[хпбмгжxpmgj])',  #[ив]з
		'[иiвvb]_?[зz3]_?[ъь]_? (?=[еёe])',		  #[ив]зъ
		'[иi]_?[сc]_?		   (?=[хпбмгжxpmgj])',  #ис
		'[пpдdg]_?[оo]_? (?> [бb]_?		 (?=[хпбмгжxpmgj])
						   | [бb]_?  [ъь]_? (?=[еёe])
						   | [зz3]_? [аa] _?
						 )?',  #по, до, пообъ, дообъ, поза, доза (двойные символы вырезаются!)
		#3
		'[пp]_?[рr]_?[оoиi]_?',  #пр[ои]
		'[зz3]_?[лl]_?[оo]_?',   #зло (злоебучая)
		'[нnh]_?[аa]_?[дdg]_?		 (?=[хпбмгжxpmgj])',  #над
		'[нnh]_?[аa]_?[дdg]_?[ъь]_?   (?=[еёe])',		  #надъ
		'[пp]_?[оo]_?[дdg]_?		  (?=[хпбмгжxpmgj])',  #под
		'[пp]_?[оo]_?[дdg]_?[ъь]_?	(?=[еёe])',		  #подъ
		'[рr]_?[аa]_?[зz3сc]_?		(?=[хпбмгжxpmgj])',  #ра[зс]
		'[рr]_?[аa]_?[зz3сc]_?[ъь]_?  (?=[еёe])',		  #ра[зс]ъ
		'[вvb]_?[оo]_?[зz3сc]_?	   (?=[хпбмгжxpmgj])',  #во[зс]
		'[вvb]_?[оo]_?[зz3сc]_?[ъь]_? (?=[еёe])',		  #во[зс]ъ
		#4
		'[нnh]_?[еe]_?[дdg]_?[оo]_?',	#недо
		'[пp]_?[еe]_?[рr]_?[еe]_?',	  #пере
		'[oо]_?[дdg]_?[нnh]_?[оo]_?',	#одно
		'[кk]_?[oо]_?[нnh]_?[оo]_?',	 #коно (коноебиться)
		'[мm]_?[уy]_?[дdg]_?[оoаa]_?',   #муд[оа] (мудаёб)
		'[oо]_?[сc]_?[тt]_?[оo]_?',	  #осто (остопиздело)
		'[дdg]_?[уy]_?[рpr]_?[оoаa]_?',  #дур[оа]
		'[хx]_?[уy]_?[дdg]_?[оoаa]_?',   #худ[оа] (худоебина)
		#5
		'[мm]_?[нnh]_?[оo]_?[гg]_?[оo]_?',	#много
		'[мm]_?[оo]_?[рpr]_?[дdg]_?[оoаa]_?', #морд[оа]
		'[мm]_?[оo]_?[зz3]_?[гg]_?[оoаa]_?',  #мозг[оа]
		'[дdg]_?[оo]_?[лl]_?[бb6]_?[оoаa]_?', #долб[оа]
	);

	static $badwords = array(
		#Слово на букву Х
		'(?<=[_\d]) {RE_PRETEXT}?
		 [hхx]_?[уyu]_?[йiеeёяюju]	 #хуй, хуя, хую, хуем, хуёвый
		 #исключения:
		 (?<! _hue(?=_)	 #HUE	 -- цветовая палитра
			| _hue(?=so_)   #hueso   -- испанское слово
			| _хуе(?=дин)   #Хуедин  -- город в Румынии
			| _hyu(?=ndai_) #Hyundai -- марка корейского автомобиля
		 )',

		#Слово на букву П
		'(?<=[_\d]) {RE_PRETEXT}?
		 [пp]_?[иi]_?[зz3]_?[дd]_?[:vowel:]',  #пизда, пизде, пиздёж, пизду, пиздюлина, пиздобол, опиздинеть, пиздых

		#Слово на букву Е
		'(?<=[_\d]) {RE_PRETEXT}?
		 [eеё]_? (?<!не[её]_) [бb6]_?(?: [уyиi]_					   #ебу, еби
									   | [ыиiоoaаеeёуy]_?[:consonant:] #ебут, ебать, ебись, ебёт, поеботина, выебываться, ёбарь
									   | [лl][оoаaыиi]				 #ебло, ебла, ебливая, еблись, еблысь
									   | [нn]_?[уy]					#ёбнул, ёбнутый
									   | [кk]_?[аa]					#взъёбка
									  )',
		'(?<=[_\d]) {RE_PRETEXT}
		 (?<=[^_\d][^_\d]|[^_\d]_[^_\d]_) [eеё]_?[бb6] (?:_|_?[аa]_?[^_\d])',  #долбоёб, дураёб, изъёб, заёб, разъебай

		#Слово на букву Б
		'(?<=[_\d]) {RE_PRETEXT}?
		 [бb6]_?[лl]_?(?:я|ya)(?: _		 #бля
								| _?[тдtd]  #блять, бляди
							  )',

		#ПИДОР
		'(?<=[_\d]) [пp]_?[иieе]_?[дdg]_?[eеaаoо]_?[rpр]',  #п[ие]д[оеа]р

		#МУДАК
		'(?<=[_\d]) [мm]_?[уy]_?[дdg]_?[аa]
					#исключения:
					(?<!_myda(?=s_))  #Chelonia mydas -- морская зеленая (суповая) черепаха
		',  #муда

		#ЖОПА
		'(?<=[_\d]) [zж]_?h?_?[оo]_?[pп]_?[aаyуыiеeoо]',  #жоп[ауыео]

		#МАНДА
		#исключения: город Мандалай, округ Мандаль, индейский народ Мандан, фамилия Мандель
		'(?<=[_\d]) [мm]_?[аa]_?[нnh]_?[дdg]_?[aаyуыiеeoо] (?<! манда(?=[лн])|манде(?=ль ))', #манд[ауыео]

		#ГОВНО
		'(?<=[_\d]) [гg]_?[оo]_?[вvb]_?[нnh]_?[оoаaяеeyу]', #говн[оаяеу]

		#FUCK
		'(?<=[_\d]) f_?u_?[cс]_?k',  #fuck

		/*
		#ЛОХ
		' л_?[оo]_?[хx]',

		#СУКА
		'[^р]_?[scс]_?[yуu]_?[kк]_?[aаiи]', #сука (кроме слова "барсука" - это животное-грызун)
		'[^р]_?[scс]_?[yуu]_?[4ч]_?[кk]',   #сучк(и) (кроме слова "барсучка")

		#ХЕР
		' {RE_PRETEXT}?[хxh]_?[еe]_?[рpr](_?[нnh]_?(я|ya)| )', #{RE_PRETEXT}хер(ня)

		#ЗАЛУПА
		' [зz3]_?[аa]_?[лl]_?[уy]_?[пp]_?[аa]',
		*/
	);

	static $re_trans = array(
		'_'			 => '\x20',					   #пробел
		'[:vowel:]'	 => '[аеиоуыэюяёaeioyu]',		 #гласные буквы
		'[:consonant:]' => '[^аеиоуыэюяёaeioyu\x20\d]',  #согласные буквы
	);
	$re_badwords = str_replace('{RE_PRETEXT}', 
							   '(?>' . implode('|', $pretext) . ')',
							   '~' . implode('|', $badwords) . '~sxuS');
	$re_badwords = strtr($re_badwords, $re_trans);

	#вырезаем все лишнее
	#скрипты не вырезаем, т.к. м.б. обходной маневр на с кодом на javascript:
	#<script>document.write('сло'+'во')</script>
	#хотя давать пользователю возможность использовать код на javascript нехорошо
	if (! function_exists('strip_tags_smart')) include_once 'strip_tags_smart.php'; #оптимизация скорости include_once
	$s = strip_tags_smart($s, null, true, array('comment', 'style', 'map', 'frameset', 'object', 'applet'));

	#заменяем html-сущности в "чистый" UTF-8
	if (! function_exists('utf8_html_entity_decode')) include_once 'utf8_html_entity_decode.php'; #оптимизация скорости include_once
	$s = utf8_html_entity_decode($s, $is_htmlspecialchars = true);

	if (! function_exists('utf8_convert_case')) include_once 'utf8_convert_case.php'; #оптимизация скорости include_once
	$s = utf8_convert_case($s, CASE_LOWER);

	/*
	Remove combining diactrical marks (Unicode 5.1).
	http://www.unicode.org/charts/symbols.html#CombiningDiacriticalMarks
	Вырезаем диакритические модифицирующие знаки.
	  Например, русские буквы Ё (U+0401) и Й (U+0419) существуют в виде монолитных символов,
	  хотя могут быть представлены и набором базового символа с последующим диакритическим знаком,
	  то есть в составной форме (Decomposed): (U+0415 U+0308), (U+0418 U+0306).
	*/
	$s = preg_replace('/(?: \xcc[\x80-\xb9]|\xcd[\x80-\xaf]  #UNICODE range: U+0300 - U+036F (for letters)
						  | \xe2\x83[\x90-\xbf]			  #UNICODE range: U+20D0 - U+20FF (for symbols)
						  | \xe1\xb7[\x80-\xbf]			  #UNICODE range: U+1DC0 - U+1DFF (supplement)
						  | \xef\xb8[\xa0-\xaf]			  #UNICODE range: U+FE20 - U+FE2F (combining half marks)
						)
					   /sxS', '', $s);

	static $trans = array(
		"\xc2\xad" => '',   #вырезаем "мягкий" перенос строки (&shy;)
		#"\xcc\x81" => '',  #вырезаем знак ударения (U+0301 «combining acute accent»)
		'/\\'	  => 'л',  #Б/\Я
		'/|'	   => 'л',  #Б/|Я
		"\xd0\xb5\xd0\xb5" => "\xd0\xb5\xd1\x91",  #ее => её
	);
	$s = strtr($s, $trans);

	#получаем в массив только буквы и цифры
	#"с_л@о#во,с\xc2\xa7лово.Слово" -> "с л о во с лово слово слово слово слово"
	preg_match_all('/(?> \xd0[\xb0-\xbf]|\xd1[\x80-\x8f\x91]  #[а-я]
					  |  [a-z\d]+
					  )+
					/sxS', $s, $m);
	$s = ' ' . implode(' ', $m[0]) . ' ';

	#убираем все повторяющиеся символы, ловим обман типа "х-у-у-й"
	#"сллоооовоо   слово  х у у й" -> "слово слово х у й"
	$s = preg_replace('/(  [\xd0\xd1][\x80-\xbf] (?:\x20)?  #оптимизированное [а-я]
						 | [a-z\d] (?:\x20)?
						 ) \\1+
					   /sxS', '$1', $s);
	#d($s);

	$result = preg_match($re_badwords, $s, $m, PREG_OFFSET_CAPTURE);
	if (function_exists('preg_last_error') && preg_last_error() !== PREG_NO_ERROR) return preg_last_error();
	if ($result === false) return 1; #PREG_INTERNAL_ERROR = 1
	if ($result)
	{
		list($word, $offset) = $m[0];
		$s1 = substr($s, 0, $offset);
		$s2 = substr($s, $offset + strlen($word));
		$delta = intval($delta);
		if ($delta < 1 || $delta > 10) $delta = 3;
		preg_match('/  (?> \x20 (?>[\xd0\xd1][\x80-\xbf]|[a-z\d]+)+ ){1,' . $delta . '}
					   \x20?
					$/sxS', $s1, $m1);
		preg_match('/^ (?>[\xd0\xd1][\x80-\xbf]|[a-z\d]+)*  #окончание
					   \x20?
					   (?> (?>[\xd0\xd1][\x80-\xbf]|[a-z\d]+)+ \x20 ){1,' . $delta . '}
					/sxS', $s2, $m2);
		$fragment = (ltrim(@$m1[0]) !== ltrim($s1) ? $continue : '') .
					trim(@$m1[0] . '[' . trim($word) . ']' . @$m2[0]) . 
					(rtrim(@$m2[0]) !== rtrim($s2) ? $continue : '');
		return $fragment;
	}
	return false;
}

?>
