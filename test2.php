<html>
<head>
 <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
 <title>檔案下載頁面</title>
</head>
<body>

<p><a href="download_sf2.php?file=testtt.txt" target="_blank">下載testtt.txt檔案</a></p>
</body>
</html>
<?php
    $file = glob("./upload/103/*.*");
    $test = explode('/',$file[0]);
    echo $test[3];
?>