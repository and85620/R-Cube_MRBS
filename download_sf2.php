<?php
/*echo $_GET['file'];
$file_name = $_GET['file'];
$file_path = "./upload/".$file_name;
echo $file_path;

return;*/
if(isset($_GET['file']))
{
    $file_name = $_GET['file'];
    $file_path = "./upload/".$file_name;
    $file_size = filesize($file_path);
    header('Pragma: public');
    header('Expires: 0');
    header('Last-Modified: ' . gmdate('D, d M Y H:i ') . ' GMT');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Cache-Control: private', false);
    header('Content-Type: application/octet-stream');
    header('Content-Length: ' . $file_size);
    header('Content-Disposition: attachment; filename="' . $file_name . '";'); // 下載視窗
    header('Content-Transfer-Encoding: binary');
    readfile($file_path);
}
/*
補充：
•$file_name: 這是給瀏覽器看的檔案名稱，也就是下載視窗會出現的那個檔名；它可以跟實際檔案的名稱不一樣！
•$file_path: 會連到實際檔案的位置，也就是該檔案在伺服器上的真實路徑。
•$file_size: 檔案的大小。
•若php.ini 的 memory_limit 設的太小，會造成網頁一直在讀取, 不會跳出下載視窗的問題。
*/
?>