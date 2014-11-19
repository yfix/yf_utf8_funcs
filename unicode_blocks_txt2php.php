<?php
function unicode_blocks_txt2php($is_named_key = true)
{
	#http://www.unicode.org/Public/5.1.0/ucd/Blocks.txt
	$lines = file('unicode_blocks.txt');
	$a = array();
	foreach ($lines as $i => $line)
	{
		$line = trim($line);
		if ($line === '' or $line{0} === '#') continue;
		list ($range, $name) = explode(';', $line);
		list ($min, $max) = explode('..', $range);
		if ($is_named_key) $a[trim($name)] = array('0x' . $min, '0x' . $max, count($a));
		else $a[] = array('0x' . $min, '0x' . $max, trim($name));
	}#foreach
	$s = var_export($a, true);
	$s = preg_replace('/\'(0x[\dA-Za-z]+)\'/sS', '$1', $s);
	$s = str_replace('array (', 'array(', $s);
	$s = "<?php\r\n#autogenerated by " . __FUNCTION__ . '() PHP function at ' . date('Y-m-d H:i:s') . ', ' . count($a) . " blocks total\r\n#charset ANSI\r\n\$unicode_blocks = " . $s . ";\r\n?>";
	//echo $s;
	return file_put_contents('unicode_blocks.php', $s);
}
#unicode_blocks_txt2php();
?>