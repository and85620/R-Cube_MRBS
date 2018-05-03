<?php
$dirname = "./dirname1";
	if(mkdir($dirname, 0700))
		echo "成功建立資料夾"."<br>";
	else
		echo "建立資料夾失敗"."<br>";
	?>