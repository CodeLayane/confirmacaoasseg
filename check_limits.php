<?php
echo "<h2>Limites ANTES do ini_set</h2>";
echo "<p>upload_max_filesize: <strong>" . ini_get('upload_max_filesize') . "</strong></p>";
echo "<p>post_max_size: <strong>" . ini_get('post_max_size') . "</strong></p>";
echo "<p>max_execution_time: <strong>" . ini_get('max_execution_time') . "</strong></p>";
echo "<p>memory_limit: <strong>" . ini_get('memory_limit') . "</strong></p>";

@ini_set('upload_max_filesize','128M');
@ini_set('post_max_size','140M');
@ini_set('max_execution_time','600');
@ini_set('memory_limit','256M');

echo "<h2>Limites DEPOIS do ini_set</h2>";
echo "<p>upload_max_filesize: <strong>" . ini_get('upload_max_filesize') . "</strong></p>";
echo "<p>post_max_size: <strong>" . ini_get('post_max_size') . "</strong></p>";
echo "<p>max_execution_time: <strong>" . ini_get('max_execution_time') . "</strong></p>";
echo "<p>memory_limit: <strong>" . ini_get('memory_limit') . "</strong></p>";

echo "<h2>Info</h2>";
echo "<p>PHP: " . phpversion() . "</p>";
echo "<p>SAPI: <strong>" . php_sapi_name() . "</strong></p>";
echo "<p>.user.ini: " . (file_exists(__DIR__.'/.user.ini')?'EXISTE':'NÃO EXISTE') . "</p>";
echo "<p>php.ini: " . (file_exists(__DIR__.'/php.ini')?'EXISTE':'NÃO EXISTE') . "</p>";

$canChange=(ini_get('upload_max_filesize')!='128M')?'<span style="color:red">NÃO MUDOU - precisa alterar no cPanel > MultiPHP INI Editor</span>':'<span style="color:green">OK!</span>';
echo "<h2>Resultado: $canChange</h2>";
echo "<p>Se não mudou, vá no cPanel > <strong>MultiPHP INI Editor</strong> > selecione a pasta > altere upload_max_filesize=128M e post_max_size=140M</p>";