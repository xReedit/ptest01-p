<?php
	$sub_i=array();
	$sub_i[7]['sub_item']='1';
	$sub_i[1]['sub_item']='2';
	foreach ($sub_i as $a) {
		echo $a['sub_item'].',';
	}
	echo json_encode($sub_i);
?>