<?php
namespace MRBS;
require "defaultincludes.inc";
require_once "version.inc";
// Check the user is authorised for this page
checkAuthorised();

$user = getUserName();
$is_admin = (authGetUserLevel($user) >= $max_level);

print_header($day, $month, $year, $area, isset($room) ? $room : null);

// echo "<h3>" . get_vocab("about_mrbs") . "</h3>\n";
echo "<h3>說明文件</h3>\n";

if (!$is_admin)
{
}
else
{
  // Restrict the configuration and server details to admins, for security reasons.
  echo "<table class=\"details has_caption list\">\n";
  echo "<caption>" . get_vocab("config_details") . "</caption>\n";
  echo "<tr><td>" . get_vocab("mrbs_version") . "</td><td>" . get_mrbs_version() . "</td></tr>\n";
  echo '<tr><td>$auth[\'type\']</td><td>' . htmlspecialchars($auth['type']) . "</td></tr>\n";
  echo '<tr><td>$auth[\'session\']</td><td>' . htmlspecialchars($auth['session']) . "</td></tr>\n";
  echo "</table>\n";


  echo "<table class=\"details has_caption list\">\n";
  echo "<caption>" . get_vocab("server_details") . "</caption>\n";
  echo "<tr><td>" . get_vocab("database") . "</td><td>" . db()->version() . "</td></tr>\n";
  echo "<tr><td>" . get_vocab("system") . "</td><td>" . php_uname() . "</td></tr>\n";
  echo "<tr><td>" . get_vocab("servertime") . "</td><td>" .
       utf8_strftime($strftime_format['datetime'], time()) .
       "</td></tr>\n";
  echo "<tr><td>" . get_vocab("server_software") . "</td><td>" . htmlspecialchars(get_server_software()) . "</td></tr>\n";
  echo "<tr><td>PHP</td><td>" . phpversion() . "</td></tr>\n";
  echo "</table>\n";
}


require_once "site_faq/site_faq" . $faqfilelang . ".html";

echo "<div style=\"text-align: center\">"; 
echo "教室地址：545南投縣埔里鎮西安路一段83號<br/>";
echo "連絡電話：(049)2910960 轉2293 （通識教育中心）<br/>";
echo "(049)290 0102 （永樂園）<br/>";
echo "連絡信箱：rschool.ncnu@gmail.com<br/>";
echo "</div>";
 //output_trailer();
 echo "</div>";