<?php
// upload_video.php - Recebe chunks de vídeo e remonta no servidor
@ini_set('max_execution_time','600');
@ini_set('memory_limit','256M');
header('Content-Type: application/json');

require_once 'config.php';
if(session_status()===PHP_SESSION_NONE) session_start();
// Permitir acesso público (inscricao.php) ou logado
$pdo=getConnection();

$action=$_POST['action']??'';
$uploadDir=__DIR__.'/uploads/videos/';
if(!is_dir($uploadDir))mkdir($uploadDir,0755,true);
$tmpDir=__DIR__.'/uploads/tmp/';
if(!is_dir($tmpDir))mkdir($tmpDir,0755,true);

switch($action){

case 'start':
    // Iniciar upload - criar ID único
    $fileName=$_POST['fileName']??'video';
    $fileSize=(int)($_POST['fileSize']??0);
    $totalChunks=(int)($_POST['totalChunks']??0);
    $ext=strtolower(pathinfo($fileName,PATHINFO_EXTENSION));
    $allowed=['mp4','mov','avi','webm','mkv','3gp','3gpp','m4v','mpg','mpeg','ogv','wmv','flv'];
    if(!in_array($ext,$allowed)&&!in_array($ext,['quicktime'])){
        // Tentar pelo mime
        $ext='mp4'; // default
    }
    if($fileSize>200*1024*1024){
        echo json_encode(['error'=>'Vídeo muito grande. Máximo: 200MB']);exit();
    }
    $uploadId=uniqid('vid_',true);
    $targetName=time().'_'.mt_rand(1000,9999).'.'.$ext;
    // Salvar info na sessão e em arquivo tmp
    $meta=['uploadId'=>$uploadId,'fileName'=>$fileName,'fileSize'=>$fileSize,'totalChunks'=>$totalChunks,'receivedChunks'=>0,'targetName'=>$targetName,'ext'=>$ext,'created'=>time()];
    file_put_contents($tmpDir.$uploadId.'.json',json_encode($meta));
    echo json_encode(['uploadId'=>$uploadId,'ok'=>true]);
    break;

case 'chunk':
    // Receber um pedaço
    $uploadId=$_POST['uploadId']??'';
    $chunkIndex=(int)($_POST['chunkIndex']??0);
    $metaFile=$tmpDir.$uploadId.'.json';
    if(!$uploadId||!file_exists($metaFile)){
        echo json_encode(['error'=>'Upload inválido']);exit();
    }
    $meta=json_decode(file_get_contents($metaFile),true);
    if(!isset($_FILES['chunk'])||$_FILES['chunk']['error']!==UPLOAD_ERR_OK){
        echo json_encode(['error'=>'Erro no chunk '.($chunkIndex+1).': código '.($_FILES['chunk']['error']??'?')]);exit();
    }
    // Salvar chunk
    $chunkFile=$tmpDir.$uploadId.'_chunk_'.str_pad($chunkIndex,5,'0',STR_PAD_LEFT);
    move_uploaded_file($_FILES['chunk']['tmp_name'],$chunkFile);
    $meta['receivedChunks']++;
    file_put_contents($metaFile,json_encode($meta));
    $pct=round(($meta['receivedChunks']/$meta['totalChunks'])*100);
    echo json_encode(['ok'=>true,'received'=>$meta['receivedChunks'],'total'=>$meta['totalChunks'],'percent'=>$pct]);
    break;

case 'finish':
    // Juntar todos os chunks
    $uploadId=$_POST['uploadId']??'';
    $metaFile=$tmpDir.$uploadId.'.json';
    if(!$uploadId||!file_exists($metaFile)){
        echo json_encode(['error'=>'Upload inválido']);exit();
    }
    $meta=json_decode(file_get_contents($metaFile),true);
    $targetPath=$uploadDir.$meta['targetName'];
    $out=fopen($targetPath,'wb');
    if(!$out){echo json_encode(['error'=>'Erro ao criar arquivo final']);exit();}
    $ok=true;
    for($i=0;$i<$meta['totalChunks'];$i++){
        $chunkFile=$tmpDir.$uploadId.'_chunk_'.str_pad($i,5,'0',STR_PAD_LEFT);
        if(!file_exists($chunkFile)){$ok=false;break;}
        $in=fopen($chunkFile,'rb');
        while(!feof($in)){fwrite($out,fread($in,8192));}
        fclose($in);
        @unlink($chunkFile);
    }
    fclose($out);
    @unlink($metaFile);
    if(!$ok){@unlink($targetPath);echo json_encode(['error'=>'Chunks incompletos']);exit();}
    echo json_encode(['ok'=>true,'video_url'=>'uploads/videos/'.$meta['targetName'],'fileName'=>$meta['fileName']]);
    break;

case 'cancel':
    $uploadId=$_POST['uploadId']??'';
    if($uploadId){
        foreach(glob($tmpDir.$uploadId.'*') as $f)@unlink($f);
    }
    echo json_encode(['ok'=>true]);
    break;

default:
    echo json_encode(['error'=>'Ação inválida']);
}

// Limpar tmps antigos (>1 hora)
foreach(glob($tmpDir.'vid_*.json') as $f){
    $m=json_decode(file_get_contents($f),true);
    if(isset($m['created'])&&$m['created']<time()-3600){
        $uid=$m['uploadId']??'';
        foreach(glob($tmpDir.$uid.'*') as $cf)@unlink($cf);
    }
}
