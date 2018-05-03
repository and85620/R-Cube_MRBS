<?php
namespace MRBS;

use MRBS\Form\Form;

if (version_compare(PHP_VERSION, '5.0.0', '<'))
{
  // We use stream_get_line() which is only available in PHP 5
  exit("Import requires PHP 5.  Your system is running PHP " . PHP_VERSION);
}

require "defaultincludes.inc";
require_once "functions_ical.inc";
require_once "mrbs_sql.inc";

$wrapper_mime_types = array('file'            => 'text/calendar',
                            'zip'             => 'application/zip',
                            'compress.zlib'   => 'application/x-gzip',
                            'compress.bzip2'  => 'application/x-bzip2');
                            
$wrapper_descriptions = array('file'            => get_vocab('import_text_file'),
                              'zip'             => get_vocab('import_zip'),
                              'compress.zlib'   => get_vocab('import_gzip'),
                              'compress.bzip2'  => get_vocab('import_bzip2'));

// Get the available compression wrappers that we can use. 
// Returns an array
function get_compression_wrappers()
{
  $result = array();
  if (function_exists('stream_get_wrappers'))
  {
    $wrappers = stream_get_wrappers();
    foreach ($wrappers as $wrapper)
    {
      if ((($wrapper == 'zip') && class_exists('ZipArchive')) ||
          (strpos($wrapper, 'compress.') === 0))
      {
        $result[] = $wrapper;
      }
    }
  }
  return $result;
}


// Gets the id of the area/room with the LOCATION property value of $location,
// creating an area and room if allowed.
// Returns FALSE if it can't find an id or create an id, with an error message in $error
function get_room_id($location, &$error)
{
  global $area_room_order, $area_room_delimiter, $area_room_create;
  global $tbl_room, $tbl_area;
  
  // If there's no delimiter we assume we've just been given a room name (that will
  // have to be unique).   Otherwise we split the location into its area and room parts
  if (strpos($location, $area_room_delimiter) === FALSE)
  {
    $location_area = '';
    $location_room = $location;
  }
  elseif ($area_room_order == 'area_room')
  {
    list($location_area, $location_room) = explode($area_room_delimiter, $location, 2);
  }
  else
  {
    list($location_room, $location_area) = explode($area_room_delimiter, $location, 2);
  }
  $location_area = trim($location_area);
  $location_room = trim($location_room);
  
  // Now search the database for the room
  
  // Case 1:  we've just been given a room name, in which case we hope it happens
  // to be unique, because if we find more than one we won't know which one is intended
  // and if we don't find one at all we won't be able to create it because we won't 
  // know which area to put it in.
  if ($location_area == '')
  {
    $sql = "SELECT COUNT(*) FROM $tbl_room WHERE room_name=?";
    $count = db()->query1($sql, array($location_room));

    if ($count == 0)
    {
      $error = "'$location_room': " . get_vocab("room_does_not_exist_no_area");
      return FALSE;
    }
    elseif ($count > 1)
    {
      $error = "'$location_room': " . get_vocab("room_not_unique_no_area");
      return FALSE;
    }
    else // we've got a unique room name
    {
      $sql = "SELECT id FROM $tbl_room WHERE room_name=? LIMIT 1";
      $id = db()->query1($sql, array($location_room));
      return $id;
    }
  }
  
  // Case 2:  we've got an area and room name
  else
  {
    // First of all get the area id
    $sql = "SELECT id
              FROM $tbl_area
             WHERE area_name=?
             LIMIT 1";
    $area_id = db()->query1($sql, array($location_area));
    if ($area_id < 0)
    {
      // The area does not exist - create it if we are allowed to
      if (!$area_room_create)
      {
        $error = get_vocab("area_does_not_exist") . " '$location_area'";
        return FALSE;
      }
      else
      {
        echo get_vocab("creating_new_area") . " '$location_area'<br>\n";
        $error_add_area = '';
        $area_id = mrbsAddArea($location_area, $error_add_area);
        if ($area_id === FALSE)
        {
          $error = get_vocab("could_not_create_area") . " '$location_area'";
          return FALSE;
        }
      }
    }
  }
  // Now we've got the area_id get the room_id
  $sql = "SELECT id
            FROM $tbl_room
           WHERE room_name=?
             AND area_id=?
           LIMIT 1";
  $room_id = db()->query1($sql, array($location_room, $area_id));
  if ($room_id < 0)
  {
    // The room does not exist - create it if we are allowed to
    if (!$area_room_create)
    {
      $error = get_vocab("room_does_not_exist") . " '$location_room'";
      return FALSE;
    }
    else
    {
      echo get_vocab("creating_new_room") . " '$location_room'<br>\n";
      $error_add_room = '';
      $room_id = mrbsAddRoom($location_room, $area_id, $error_add_room);
      if ($room_id === FALSE)
      {
        $error = get_vocab("could_not_create_room") . " '$location_room'";
        return FALSE;
      }
    }
  }
  return $room_id;
}


// Get the next line, after unfolding, from the stream.
// Returns FALSE when EOF is reached
function get_unfolded_line($handle)
{
  static $buffer_line;

  // If there's something in the buffer line left over
  // from the last call, then start with that.
  if (isset($buffer_line))
  {
    $unfolded_line = $buffer_line;
    $buffer_line = NULL;
  }

  // Theoretically the line should be folded if it's longer than 75 octets
  // but just in case the file has been created without using folding we
  // will read a large number (4096) of bytes to make sure that we get as
  // far as the CRLF.
  while (FALSE !== ($line = stream_get_line($handle, 4096, "\r\n")))
  {
    if (!isset($unfolded_line))
    {
      $unfolded_line = $line;
    }
    else
    {  
      $first_char = utf8_substr($line, 0, 1);
      // If the first character of the line is a space or tab then it's
      // part of a fold
      if (($first_char == " ") || ($first_char == "\t"))
      {
        $unfolded_line .= utf8_substr($line, 1);
      }
      // Otherwise we've reached the start of the next unfolded line, so
      // save it for next time and finish
      else
      {
        $buffer_line = $line;
        break;
      }
    }
  }
  
  return (isset($unfolded_line)) ? $unfolded_line : FALSE;
}


// Get the next event from the stream.
// Returns FALSE if EOF has been reached, or else an array
// of lines for the event.  The BEGIN:VEVENT and END:VEVENT
// lines are not included in the array. 
function get_event($handle)
{
  // Advance to the beginning of the event
  while ((FALSE !== ($ical_line = get_unfolded_line($handle))) && ($ical_line != 'BEGIN:VEVENT'))
  {
  }

  // No more events
  if ($ical_line === FALSE)
  {
    return FALSE;
  }
  // Get the event
  $vevent = array();
  while ((FALSE !== ($ical_line = get_unfolded_line($handle))) && ($ical_line != 'END:VEVENT'))
  {
    $vevent[] = $ical_line;
  }

  return $vevent;
}


// Add a VEVENT to MRBS.   Returns TRUE on success, FALSE on failure
function process_event($vevent)
{
  global $import_default_room, $import_default_type, $skip;
  global $morningstarts, $morningstarts_minutes, $resolution;
  global $booking_types;
  
  // We are going to cache the settings ($resolution etc.) for the rooms
  // in order to avoid lots of database lookups
  static $room_settings = array();
  
  // Set up the booking with some defaults
  $booking = array();
  $booking['status'] = 0;
  $booking['rep_type'] = REP_NONE;
  $booking['type'] = $import_default_type;
  $booking['room_id'] = $import_default_room;
  
  // Parse all the lines first because we'll need to get the start date
  // for calculating some of the other settings
  $properties = array();
  $problems = array();

  $line = current($vevent);
  while ($line !== FALSE)
  {
    $property = parse_ical_property($line);
    // Ignore any sub-components (eg a VALARM inside a VEVENT) as MRBS does not
    // yet handle things like reminders.  Skip through to the end of the sub-
    // component.   Just in case you can have sub-components at a greater depth
    // than 1 (not sure if you can), make sure we've got to the matching END.
    if ($property['name'] != 'BEGIN')
    {
      $properties[$property['name']] = array('params' => $property['params'],
                                             'value' => $property['value']);
    }
    else
    {
      $component = $property['value'];
      while (!(($property['name'] == 'END') && ($property['value'] == $component)) &&
             ($line = next($vevent)))
      {
        $property = parse_ical_property($line);;
      }
    }
    $line = next($vevent);
  }
  // Get the start time because we'll need it later
  if (!isset($properties['DTSTART']))
  {
    trigger_error("No DTSTART", E_USER_WARNING);
  }
  else
  {
    $booking['start_time'] = get_time($properties['DTSTART']['value'],
                                      $properties['DTSTART']['params']);
  }
  // Now go through the rest of the properties
  foreach($properties as $name => $details)
  {
    switch ($name)
    {
      case 'ORGANIZER':
        $booking['create_by'] = get_create_by($details['value']);
        $booking['modified_by'] = '';
        break;
      case 'SUMMARY':
        $booking['name'] = $details['value'];
        break;
      case 'DESCRIPTION':
        $booking['description'] = $details['value'];
        break;
      case 'LOCATION':
        $error = '';
        $booking['room_id'] = get_room_id($details['value'], $error);
        if ($booking['room_id'] === FALSE)
        {
          $problems[] = $error;
        }
        break;
      case 'DTEND':
        $booking['end_time'] = get_time($details['value'], $details['params']);
        break;
      case 'DURATION':
        trigger_error("DURATION not yet supported by MRBS", E_USER_WARNING);
        break;
      case 'RRULE':
        $rrule_errors = array();
        $repeat_details = get_repeat_details($details['value'], $booking['start_time'], $rrule_errors);
        if ($repeat_details === FALSE)
        {
          $problems = array_merge($problems, $rrule_errors);
        }
        else
        {
          foreach ($repeat_details as $key => $value)
          {
            $booking[$key] = $value;
          }
        }
        break;
      case 'CLASS':
        if (in_array($details['value'], array('PRIVATE', 'CONFIDENTIAL')))
        {
          $booking['status'] |= STATUS_PRIVATE;
        }
        break;
      case 'STATUS':
        if ($details['value'] == 'TENTATIVE')
        {
          $booking['status'] |= STATUS_TENTATIVE;
        }
        break;
      case 'X-MRBS-TYPE':
        foreach($booking_types as $type)
        {
          if ($details['value'] == get_type_vocab($type))
          {
            $booking['type'] = $type;
            break;
          }
        }
        break;
      case 'UID':
        $booking['ical_uid'] = $details['value'];
        break;
      case 'SEQUENCE':
        $booking['ical_sequence'] = $details['value'];
        break;
      case 'LAST-MODIFIED':
        // We probably ought to do something with LAST-MODIFIED and use it
        // for the timestamp field
        break;
      default:
        break;
    }
  }
  
  // If we didn't manage to work out a username then just put the booking
  // under the name of the current user
  if (!isset($booking['create_by']))
  {
    $booking['create_by'] = getUserName();
  }
  
  // A SUMMARY is optional in RFC 5545, however a brief description is mandatory
  // in MRBS.   So if the VEVENT didn't include a name, we'll give it one
  if (!isset($booking['name']))
  {
    $booking['name'] = "Imported event - no SUMMARY name";
  }
  
  // On the other hand a UID is mandatory in RFC 5545.   We'll be lenient and
  // provide one if it is missing
  if (!isset($booking['ical_uid']))
  {
    $booking['ical_uid'] = generate_global_uid($booking['name']);
    $booking['sequence'] = 0;  // and we'll start the sequence from 0
  }
  
  // LOCATION is optional in RFC 5545 but is obviously mandatory in MRBS.
  // If there is no LOCATION property we use the default_room specified on
  // the form, but if there is no default room (most likely because no rooms
  // have been created) then this error message is created).
  if (!isset($booking['room_id']))
  {
    $problems[] = get_vocab("no_LOCATION");
  }
  
  if (empty($problems))
  {
    // Get the area settings for this room, if we haven't got them already
    if (!isset($room_settings[$booking['room_id']]))
    {
      get_area_settings(get_area($booking['room_id']));
      $room_settings[$booking['room_id']]['morningstarts'] = $morningstarts;
      $room_settings[$booking['room_id']]['morningstarts_minutes'] = $morningstarts_minutes;
      $room_settings[$booking['room_id']]['resolution'] = $resolution;
    }
    // Round the start and end times to slot boundaries
    $date = getdate($booking['start_time']);
    $m = $date['mon'];
    $d = $date['mday'];
    $y = $date['year'];
    $am7 = mktime($room_settings[$booking['room_id']]['morningstarts'],
                  $room_settings[$booking['room_id']]['morningstarts_minutes'],
                  0, $m, $d, $y);
    $booking['start_time'] = round_t_down($booking['start_time'],
                                          $room_settings[$booking['room_id']]['resolution'],
                                          $am7);
    $booking['end_time'] = round_t_up($booking['end_time'],
                                      $room_settings[$booking['room_id']]['resolution'],
                                      $am7);
    // Make the bookings
    $bookings = array($booking);
    $result = mrbsMakeBookings($bookings, NULL, FALSE, $skip);
    if ($result['valid_booking'])
    {
      return TRUE;
    }
  }
  // There were problems - list them
  echo "<div class=\"problem_report\">\n";
  echo get_vocab("could_not_import") . " UID:" . htmlspecialchars($booking['ical_uid']);
  echo "<ul>\n";
  foreach ($problems as $problem)
  {
    echo "<li>" . htmlspecialchars($problem) . "</li>\n";
  }
  if (!empty($result['violations']['errors']))
  {
    echo "<li>" . get_vocab("rules_broken") . "\n";
    echo "<ul>\n";
    foreach ($result['violations']['errors'] as $rule)
    {
      echo "<li>$rule</li>\n";
    }
    echo "</ul></li>\n";
  }
  if (!empty($result['conflicts']))
  {
    echo "<li>" . get_vocab("conflict"). "\n";
    echo "<ul>\n";
    foreach ($result['conflicts'] as $conflict)
    {
      echo "<li>$conflict</li>\n";
    }
    echo "</ul></li>\n";
  }
  echo "</ul>\n";
  echo "</div>\n";
  
  return FALSE;
}


function get_file_details_calendar($file)
{
  $files = array();
  $files[] = array('name'     => $file['name'],
                   'tmp_name' => $file['tmp_name'],
                   'size'     => filesize($file['tmp_name']));
  return $files;
}


function get_file_details_bzip2($file)
{
  // It's not possible to get the uncompressed size of a bzip2 file without first
  // decompressing the whole file
  $files = array();
  $files[] = array('name'     => $file['name'],
                   'tmp_name' => $file['tmp_name'],
                   'size'     => NULL);
  return $files;
}

function get_file_details_gzip($file)
{
  // Get the uncompressed size of the gzip file which is stored in the last four 
  // bytes of the file, little-endian
  if (FALSE !== ($handle = fopen($file['tmp_name'], 'rb')))
  {
    fseek($handle, -4, SEEK_END);
    $buffer = fread($handle, 4);
    $size_array = unpack('V', $buffer);
    $size = end($size_array);
    fclose($handle);
  }
  else
  {
    $size = NULL;
  }
  $files = array();
  $files[] = array('name'     => $file['name'],
                   'tmp_name' => $file['tmp_name'],
                   'size'     => $size);
  return $files;
}


function get_file_details_zip($file)
{
  $files = array();
  
  if (class_exists('ZipArchive'))
  {
    $zip = new ZipArchive();

    if (TRUE === ($result = $zip->open($file['tmp_name'])))
    {
      for ($i=0; $i<$zip->numFiles; $i++)
      {
        $details = array();
        $stats = $zip->statIndex($i);
        $details['name']     = $stats['name'];
        $details['tmp_name'] = $file['tmp_name'] . '#' . $stats['name'];
        $details['size']     = $stats['size'];
        $files[] = $details;
      }
    }
    else
    {
      // Try and convert the error code into something a bit more
      // meaningful.
      //
      // It's safe to use ReflectionClass (PHP 5) as we already know that
      // ZipArchive (PHP 5.2.0) exists
      $reflection = new ReflectionClass('ZipArchive');
      $constants = $reflection->getConstants();
      foreach ($constants as $key => $value)
      {
        if (($result === $value) && (strpos($key, 'ER_') === 0))
        {
          $error_code = $key;
          break;
        }
      }
      $message = "ZipArchive::open() failed with ";
      if (isset($error_code))
      {
        $message .= "error code $error_code.";
        // There is a problem on IIS when opening a ZipArchive file that is in
        // C:\Windows\Temp.   ER_OPEN will be returned unless the user IUSR_XXXX
        // has 'List Folder' permission.
        // See the user notes at http://php.net/manual/en/ziparchive.open.php
        // and also https://bugs.php.net/bug.php?id=54128
        if ($result == ZipArchive::ER_OPEN)
        {
          $message .= " If your server is running Windows, check the permissions on " . 
                      dirname($file['tmp_name']) . ". 'List Folder' permission is required " .
                      "for user IUSR_XXXX.";
        }
      }
      else
      {
        $message .= "unknown error code '$result'";
      }
      trigger_error($message, E_USER_WARNING);
    }
  }
  else
  {
    trigger_error("Could not open zip archive - the ZipArchive class does not exist on this system", E_USER_WARNING);
  }
  return $files;
}


function get_archive_details($file)
{
  $result = array();
  switch ($file['type'])
  {
    case 'text/calendar':
    case 'text/html':
    case 'text/plain':
    case 'application/x-download':
      $result['wrapper'] = 'file';
      $result['files'] = get_file_details_calendar($file);
      break;
    case 'application/x-bzip2':
      $result['wrapper'] = 'compress.bzip2';
      $result['files'] = get_file_details_bzip2($file);
      break;
    case 'application/x-gzip':
      $result['wrapper'] = 'compress.zlib';
      $result['files'] = get_file_details_gzip($file);
      break;
    case 'application/zip':
      $result['wrapper'] = 'zip';
      $result['files'] = get_file_details_zip($file);
      break;
    default:
      $result = FALSE;
      trigger_error("Unknown file type '" . $file['type'] . "'", E_USER_NOTICE);
      break;
  }
  return $result;
}


$import = get_form_var('import', 'string');
$import_default_room = get_form_var('import_default_room', 'int');
$area_room_order = get_form_var('area_room_order', 'string', 'area_room');
$area_room_delimiter = get_form_var('area_room_delimiter', 'string', $default_area_room_delimiter);
$area_room_create = get_form_var('area_room_create', 'string', '0');
$import_default_type = get_form_var('import_default_type', 'string', $default_type);
$skip = get_form_var('skip', 'string', ((empty($skip_default)) ? '0' : '1'));

// Check the CSRF token if we're being asked to import data
if (!empty($import))
{
  Form::checkToken();
}

// Check the user is authorised for this page
checkAuthorised();

print_header($day, $month, $year, $area, $room);


// PHASE 2 - Process the files
// ---------------------------

if (!empty($import))
{
  if ($_FILES['upload_file']['error'] !== UPLOAD_ERR_OK)
  {
    echo "<p>\n";
    echo get_vocab("upload_failed");
    switch($_FILES['upload_file']['error'])
    {
      case UPLOAD_ERR_INI_SIZE:
        echo "<br>\n";
        echo get_vocab("max_allowed_file_size") . " " . ini_get('upload_max_filesize');
        break;
      case UPLOAD_ERR_NO_FILE:
        echo "<br>\n";
        echo get_vocab("no_file");
        break;
      default:
        // None of the other possible errors would make much sense to the user, but should be reported
        trigger_error($_FILES['upload_file']['error'], E_USER_NOTICE);
        break;
    }
    echo "</p>\n";
  }
  elseif (!is_uploaded_file($_FILES['upload_file']['tmp_name']))
  {
    // This should not happen and if it does may mean that somebody is messing about
    echo "<p>\n";
    echo get_vocab("upload_failed");
    echo "</p>\n";
    trigger_error("Attempt to import a file that has not been uploaded", E_USER_WARNING);
  }
  // We've got a file
  else
  {
    $archive = get_archive_details($_FILES['upload_file']);

    if ($archive === FALSE)
    {
      echo "<p>" . get_vocab("could_not_process") . "</p>\n";
    }
    else
    {
      foreach ($archive['files'] as $file)
      {
        echo "<h3>" . $file['name'] . "</h3>";
        
        $n_success = 0;
        $n_failure = 0;
        
        $handle = fopen($archive['wrapper'] . '://' . $file['tmp_name'], 'rb');
        
        if ($handle === FALSE)
        {
          echo "<p>" . get_vocab("could_not_process") . "</p>\n";
        }
        else
        {
          while (FALSE !== ($vevent = get_event($handle)))
          {
            (process_event($vevent)) ? $n_success++ : $n_failure++;
          }
          
          fclose($handle);
          
          echo "<p>\n";
          echo "$n_success " . get_vocab("events_imported");
          if ($n_failure > 0)
          {
            echo "<br>\n$n_failure " . get_vocab("events_not_imported");
          }
          echo "</p>\n";
        }
      }
    }
  }
}

// PHASE 1 - Get the user input
// ----------------------------

$compression_wrappers = get_compression_wrappers();

echo "<form class=\"form_general\" method=\"POST\" enctype=\"multipart/form-data\" action=\"" . htmlspecialchars(this_page()) . "\">\n";
echo Form::getTokenHTML() . "\n";
echo "<fieldset class=\"admin\">\n";
echo "<legend>" . get_vocab("import_icalendar") . "</legend>\n";

echo "<p>\n" . get_vocab("import_intro") . "</p>\n";
echo "<p>\n" . get_vocab("supported_file_types") . "</p>\n";
echo "<ul>\n";
echo "<li>" . $wrapper_descriptions['file'] . "</li>\n";
foreach ($compression_wrappers as $compression_wrapper)
{
  echo "<li>" . $wrapper_descriptions[$compression_wrapper] . "</li>\n";
}
echo "</ul>\n";
  
echo "<div>\n";
echo "<label for=\"upload_file\">" . get_vocab("file_name") . "</label>\n";

$accept_mime_types = array();
foreach ($compression_wrappers as $compression_wrapper)
{
  $accept_mime_types[] = $wrapper_mime_types[$compression_wrapper];
}
// 'file' will always be available.  Put it at the beginning of the array.
array_unshift($accept_mime_types, $wrapper_mime_types['file']);

echo "<input type=\"file\" accept=\"" . implode(',', $accept_mime_types). "\" name=\"upload_file\" id=\"upload_file\">\n";
echo "</div>\n";

echo "<fieldset>\n";
echo "<legend>" . get_vocab("area_room_settings") . "</legend>\n";

// Default room
$areas = get_area_names($all=true);
if (count($areas) > 0)
{
  $options = array();
  
  foreach($areas as $area_id => $area_name)
  {
    $rooms = get_room_names($area_id, $all=true);
    if (count($rooms) > 0)
    {
      $options[$area_name] = array();
      foreach($rooms as $room_id => $room_name)
      {
        $options[$area_name][$room_id] = $room_name;
      }
    }
  }
  
  if (count($options) > 0)
  {
    echo "<div>\n";
    $params = array('name'        => 'import_default_room',
                    'label'       => get_vocab('default_room'),
                    'label_title' => get_vocab('default_room_note'),
                    'options'     => $options,
                    'force_assoc' => true,
                    'value'       => $default_room);
    generate_select($params);
    echo "</div>\n";
  }
}


// Area-room order
echo "<div>\n";
$params = array('label'       => get_vocab('area_room_order'),
                'label_title' => get_vocab('area_room_order_note'),
                'name'        => 'area_room_order',
                'options'     => array('area_room' => get_vocab('area_room'),
                                       'room_area' => get_vocab('room_area')),
                'value'       => $area_room_order);
generate_radio_group($params);
echo "</div>\n";

// Area-room delimiter
echo "<div>\n";
$params = array('label'       => get_vocab('area_room_delimiter'),
                'label_title' => get_vocab('area_room_delimiter_note'),
                'name'        => 'area_room_delimiter',
                'value'       => $area_room_delimiter);
generate_input($params);
echo "</div>\n";

// Area/room create
echo "<div>\n";
$params = array('label' => get_vocab('area_room_create'),
                'name'  => 'area_room_create',
                'value' => $area_room_create);
generate_checkbox($params);
echo "</div>\n";

echo "</fieldset>\n";


echo "<fieldset>\n";
echo "<legend>" . get_vocab("other_settings") . "</legend>\n";

// Default type
echo "<div>\n";
$options = array();
foreach ($booking_types as $type)
{
  $options[$type] = get_type_vocab($type);
}
$params = array('label'       => get_vocab('default_type'),
                'name'        => 'import_default_type',
                'options'     => $options,
                'force_assoc' => true,
                'value'       => $import_default_type);
generate_select($params);
echo "</div>\n";

// Skip conflicts
echo "<div>\n";
$params = array('label' => get_vocab('skip_conflicts'),
                'name'  => 'skip',
                'value' => $skip);
generate_checkbox($params);
echo "</div>\n";

echo "</fieldset>\n";

// The Submit button
echo "<div id=\"import_submit\">\n";
echo "<input class=\"submit\" type=\"submit\" name=\"import\" value=\"" . get_vocab("import") . "\">\n";
echo "</div>\n";

echo "</fieldset>\n";

echo "</form>\n";
  
output_trailer();

