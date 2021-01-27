<?php //DO NOT DELETE THIS FILE.
$path=['','users/','usersc/','manager/','api/'];
//Only add or remove values in the $path variable separated by commas above

$abs_app_root=$_SERVER['DOCUMENT_ROOT'];
$self_path=explode("/", $_SERVER['PHP_SELF']);
$self_path_length=count($self_path);
$file_found=FALSE;

for($i = 1; $i < $self_path_length; $i++){
	array_splice($self_path, $self_path_length-$i, $i);
	$url_app_root=implode("/",$self_path)."/";

	if (file_exists($abs_app_root.$url_app_root.'z_root.php')){
		$file_found=TRUE;
		break;
	}else{
		$file_found=FALSE;
	}
}
//redirect back to Userspice URL root (usually /)
header('Location: '.$url_app_root);
exit;

?>
