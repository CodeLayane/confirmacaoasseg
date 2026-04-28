<?php
require_once 'config.php';
$pdo=getConnection();

echo "<h2>1. Coluna video_url existe?</h2>";
$cols=array_column($pdo->query("SHOW COLUMNS FROM participantes")->fetchAll(),'Field');
if(in_array('video_url',$cols)){
    echo "<p style='color:green'>✅ SIM - coluna existe</p>";
}else{
    echo "<p style='color:red'>❌ NÃO - EXECUTE ESTE SQL NO PHPMYADMIN:</p>";
    echo "<pre>ALTER TABLE participantes ADD COLUMN video_url VARCHAR(500) DEFAULT NULL AFTER campos_extras;</pre>";
}

echo "<h2>2. Participantes com vídeo</h2>";
if(in_array('video_url',$cols)){
    $s=$pdo->query("SELECT id,nome,video_url FROM participantes WHERE video_url IS NOT NULL AND video_url!='' ORDER BY id DESC LIMIT 10");
    $rows=$s->fetchAll();
    if(empty($rows)){
        echo "<p style='color:orange'>⚠️ Nenhum participante tem video_url preenchido</p>";
    }else{
        echo "<table border=1 cellpadding=6><tr><th>ID</th><th>Nome</th><th>video_url</th><th>Arquivo existe?</th></tr>";
        foreach($rows as $r){
            $exists=file_exists(__DIR__.'/'.$r['video_url'])?'✅ SIM':'❌ NÃO';
            echo "<tr><td>{$r['id']}</td><td>{$r['nome']}</td><td>{$r['video_url']}</td><td>{$exists}</td></tr>";
        }
        echo "</table>";
    }
}

echo "<h2>3. Pasta uploads/videos</h2>";
$vdir=__DIR__.'/uploads/videos';
echo "<p>Path: $vdir</p>";
echo "<p>Existe: ".(is_dir($vdir)?'✅ SIM':'❌ NÃO - CRIE A PASTA')."</p>";
if(is_dir($vdir)){
    echo "<p>Gravável: ".(is_writable($vdir)?'✅ SIM':'❌ NÃO - chmod 755')."</p>";
    $files=glob($vdir.'/*');
    echo "<p>Arquivos: ".count($files)."</p>";
    foreach(array_slice($files,0,5) as $f){
        echo "<p> - ".basename($f)." (".round(filesize($f)/1024/1024,1)."MB)</p>";
    }
}

echo "<h2>4. Pasta uploads/tmp</h2>";
$tdir=__DIR__.'/uploads/tmp';
echo "<p>Existe: ".(is_dir($tdir)?'✅ SIM':'❌ NÃO - CRIE A PASTA')."</p>";
if(is_dir($tdir)) echo "<p>Gravável: ".(is_writable($tdir)?'✅ SIM':'❌ NÃO')."</p>";
