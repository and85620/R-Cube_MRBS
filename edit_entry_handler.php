<?php
namespace MRBS;

require "defaultincludes.inc";
require_once "mrbs_sql.inc";
require_once "functions_ical.inc";

use MRBS\Form\Form;

function invalid_booking($message)
{
  global $day, $month, $year, $area, $room;
  
  print_header($day, $month, $year, $area, isset($room) ? $room : null);
  echo "<h1>" . get_vocab('invalid_booking') . "</h1>\n";
  echo "<p>$message</p>\n";
  // Print footer and exit
  print_footer(TRUE);
}

$ajax = get_form_var('ajax', 'int');
if ($ajax && !checkAuthorised(TRUE))
{
  exit;
}


// Check the CSRF token
Form::checkToken();


// (1) Check the user is authorised for this page
//  ---------------------------------------------
checkAuthorised();

// Also need to know whether they have admin rights
$user = getUserName();
$is_admin = (authGetUserLevel($user) >= 2);



// (2) Get the form variables
// --------------------------

// NOTE:  the code on this page assumes that array form variables are passed
// as an array of values, rather than an array indexed by value.   This is
// particularly important for checkbox arrays whicgh should be formed like this:
//
//    <input type="checkbox" name="foo[]" value="n">
//    <input type="checkbox" name="foo[]" value="m">
//
// and not like this:
//
//    <input type="checkbox" name="foo[n]" value="1">
//    <input type="checkbox" name="foo[m]" value="1">


// This page can be called with an Ajax call.  In this case it just checks
// the validity of a proposed booking and does not make the booking.

// Get non-standard form variables
$formvars = array('create_by'          => 'string',
                  'name'               => 'string',
                  'description'        => 'string',
                  'start_seconds'      => 'int',
                  'start_day'          => 'int',
                  'start_month'        => 'int',
                  'start_year'         => 'int',
                  'end_seconds'        => 'int',
                  'end_day'            => 'int',
                  'end_month'          => 'int',
                  'end_year'           => 'int',
                  'all_day'            => 'string',  // bool, actually
                  'type'               => 'string',
                  'rooms'              => 'array',
                  'original_room_id'   => 'int',
                  'ical_uid'           => 'string',
                  'ical_sequence'      => 'int',
                  'ical_recur_id'      => 'string',
                  'returl'             => 'string',
                  'id'                 => 'int',
                  'rep_id'             => 'int',
                  'edit_type'          => 'string',
                  'rep_type'           => 'int',
                  'rep_end_day'        => 'int',
                  'rep_end_month'      => 'int',
                  'rep_end_year'       => 'int',
                  'rep_id'             => 'int',
                  'rep_day'            => 'array',   // array of bools
                  'rep_num_weeks'      => 'int',
                  'month_type'         => 'int',
                  'month_absolute'     => 'int',
                  'month_relative_ord' => 'string',
                  'month_relative_day' => 'string',
                  'skip'               => 'string',  // bool, actually
                  'no_mail'            => 'string',  // bool, actually
                  'private'            => 'string',  // bool, actually
                  'confirmed'          => 'string',
                  'back_button'        => 'string',
                  'timetohighlight'    => 'int',
                  'page'               => 'string',
                  'commit'             => 'string');
      
foreach($formvars as $var => $var_type)
{
  $$var = get_form_var($var, $var_type);
  // Trim the strings
  if (is_string($$var))
  {
    $$var = trim($$var);
  }
}

// BACK:  we didn't really want to be here - send them to the returl
if (!empty($back_button))
{
  if (empty($returl))
  {
    $returl = "index.php";
  }
  header("Location: $returl");
  exit();
}

// Get custom form variables
$custom_fields = array();

// Get the information about the fields in the entry table
$fields = db()->field_info($tbl_entry);
          
foreach($fields as $field)
{
  if (!in_array($field['name'], $standard_fields['entry']))
  {
    switch($field['nature'])
    {
      case 'character':
        $f_type = 'string';
        break;
      case 'integer':
        $f_type = 'int';
        break;
      // We can only really deal with the types above at the moment
      default:
        $f_type = 'string';
        break;
    }
    $var = VAR_PREFIX . $field['name'];
    $custom_fields[$field['name']] = get_form_var($var, $f_type);
    if (($f_type == 'int') && ($custom_fields[$field['name']] === ''))
    {
      $custom_fields[$field['name']] = NULL;
    }
    // Trim any strings
    if (is_string($custom_fields[$field['name']]))
    {
      $custom_fields[$field['name']] = trim($custom_fields[$field['name']]);
    }
  }
}



// (3) Clean up the form variables
// -------------------------------

// Form validation checks.   Normally checked for client side.
// Don't bother with them if this is an Ajax request.
if (!$ajax)
{
  if ($name === '')
  {
    invalid_booking(get_vocab('must_set_description'));
  }       

  if (empty($rooms))
  {
    invalid_booking(get_vocab('no_rooms_selected'));
  }

  if (($rep_type == REP_WEEKLY) && ($rep_num_weeks < 1))
  {
    invalid_booking(get_vocab('you_have_not_entered') . " " . get_vocab("useful_n-weekly_value"));
  }
  

  if (count($is_mandatory_field))
  {
    foreach ($is_mandatory_field as $field => $value)
    {
      $field = preg_replace('/^entry\./', '', $field);
      if ($value)
      {
        if ((in_array($field, $standard_fields['entry']) && ($$field === '')) ||
            (array_key_exists($field, $custom_fields) && ($custom_fields[$field] === '')))
        {
          invalid_booking(get_vocab('missing_mandatory_field') . ' "' .
                          get_loc_field_name($tbl_entry, $field) . '"');
        }
      }
    }
  }        
}

if (!isset($type))
{
  $type = $default_type;
}

if (isset($month_relative_ord) && isset($month_relative_day))
{
  $month_relative = $month_relative_ord . $month_relative_day;
}

// Handle private booking
// Enforce config file settings if needed
if ($private_mandatory) 
{
  $isprivate = $private_default;
}
else
{
  $isprivate = ($private) ? TRUE : FALSE;
}

// The id must be either an integer or NULL, so that subsequent code that tests whether
// isset($id) works.  (I suppose one could use !empty instead, but there's always the
// possibility that sites have allowed 0 in their auto-increment/serial columns.)
if (isset($id) && ($id == ''))
{
  unset($id);
}


if (empty($rooms))
{
  trigger_error('Internal error: no rooms specified', E_USER_WARNING);
}
else
{
  // Make sure the area corresponds to the room that is being booked
  $area = get_area($rooms[0]);
  get_area_settings($area);  // Update the area settings
}
// and that $room is in $area
if (get_area($room) != $area)
{
  $room = get_default_room($area);
}

// If they're not an admin and multi-day bookings are not allowed, then
// set the end date to the start date
if (!$is_admin && $auth['only_admin_can_book_multiday'])
{
  $end_day = $start_day;
  $end_month = $start_month;
  $end_year = $start_year;
}

// Check that they really are allowed to set $no_mail;
if ($no_mail)
{
  if (!$mail_settings['allow_no_mail'] &&
      (!$is_admin || !$mail_settings['allow_admins_no_mail']))
  {
    $no_mail = FALSE;
  }
}

// If this is an Ajax request and we're being asked to commit the booking, then
// we'll only have been supplied with parameters that need to be changed.  Fill in
// the rest from the existing boking information.
// Note: we assume that 
// (1) this is not a series (we can't cope with them yet)
// (2) we always get passed start_seconds and end_seconds in the Ajax data
if ($ajax && $commit)
{
  $old_booking = get_booking_info($id, FALSE);
  foreach ($formvars as $var => $var_type)
  {
    if (!isset($$var) || (($var_type == 'array') && empty($$var)))
    {
      switch ($var)
      {
        case 'rooms':
          $rooms = array($old_booking['room_id']);
          break;
        case 'original_room_id':
          $$var = $old_booking['room_id'];
          break;
        case 'private':
          $$var = $old_booking['status'] & STATUS_PRIVATE;
          break;
        case 'confirmed':
          $$var = !($old_booking['status'] & STATUS_TENTATIVE);
          break;
        // In the calculation of $start_seconds and $end_seconds below we need to take
        // care of the case when 0000 on the day in question is across a DST boundary
        // from the current time, ie the days on which DST starts and ends.
        case 'start_seconds';
          $date = getdate($old_booking['start_time']);
          $start_year = $date['year'];
          $start_month = $date['mon'];
          $start_day = $date['mday'];
          $start_daystart = mktime(0, 0, 0, $start_month, $start_day, $start_year);
          $old_start = $old_booking['start_time'];
          $start_seconds = $old_start - $start_daystart;
          $start_seconds -= cross_dst($start_daystart, $old_start);
          break;
        case 'end_seconds';
          $date = getdate($old_booking['end_time']);
          $end_year = $date['year'];
          $end_month = $date['mon'];
          $end_day = $date['mday'];
          $end_daystart = mktime(0, 0, 0, $end_month, $end_day, $end_year);
          $old_end = $old_booking['end_time'];
          $end_seconds = $old_end - $end_daystart;
          $end_seconds -= cross_dst($end_daystart, $old_end);
          // When using periods end_seconds is actually the start of the last period
          if ($enable_periods)
          {
            $end_seconds -= 60;
          }
          break;
        default:
          if (array_key_exists($var, $old_booking))
          {
            $$var = $old_booking[$var];
          }
          break;
      }
    }
  }

  // Now the custom fields
  $custom_fields = array();
  foreach ($fields as $field)
  {
    if (!in_array($field['name'], $standard_fields['entry']))
    {
      $custom_fields[$field['name']] = $old_booking[$field['name']];
    }
  }
}


// Truncate any fields that have a maximum length as a precaution.
// Although the MAXLENGTH attribute is used in the <input> tag, this can
// sometimes be ignored by the browser, for example by Firefox when 
// autocompletion is used.  The user could also edit the HTML and remove
// the MAXLENGTH attribute.    Another problem is that the <datalist> tag
// does not accept a maxlength attribute.  Passing an oversize string to some
// databases (eg some versions of PostgreSQL) results in an SQL error,
// rather than silent truncation of the string.
//
// We truncate to a maximum number of UTF8 characters rather than bytes.
// This is OK in current versions of MySQL and PostgreSQL, though in earler
// versions of MySQL (I haven't checked PostgreSQL) this could cause problems
// as a VARCHAR(n) was n bytes long rather than n characters.
foreach ($maxlength as $key => $length)
{
  list($table, $field) = explode('.', $key, 2);
  if ($table == 'entry')
  {
    // Custom fields are held in their own array, so we need to handle them
    // slightly differently. (Should probably change the way they are handled
    // sometime to be just like any other variables).
    if (array_key_exists($field, $custom_fields))
    {
      $custom_fields[$field] = utf8_substr($custom_fields[$field], 0, $length);
    }
    elseif (isset($$field))
    {
      $$field = utf8_substr($$field, 0, $length);
    }
  }
}

// When All Day is checked, $start_seconds and $end_seconds are disabled and so won't
// get passed through by the form.   We therefore need to set them.
if (!empty($all_day))
{
  if ($enable_periods)
  {
    $start_seconds = 12 * SECONDS_PER_HOUR;
    // This is actually the start of the last period, which is what the form would
    // have returned.   It will get corrected in a moment.
    $end_seconds = $start_seconds + ((count($periods) - 1) * 60);
  }
  else
  {
    $start_seconds = (($morningstarts * 60) + $morningstarts_minutes) * 60;
    $end_seconds = (($eveningends * 60) + $eveningends_minutes) *60;
    $end_seconds += $resolution;  // We want the end of the last slot, not the beginning
    if (day_past_midnight())
    {
      $end_seconds += SECONDS_PER_DAY;
    }
  }
}

// If we're operating on a booking day that stretches past midnight, it's more convenient
// for the sections past midnight to be shown as being on the day before.  That way the
// $returl will end up taking us back to the day we started on
if (day_past_midnight())
{
  $end_last = (((($eveningends * 60) + $eveningends_minutes) *60) + $resolution) % SECONDS_PER_DAY;
  if ($start_seconds < $end_last)
  {
    $start_seconds += SECONDS_PER_DAY;
    $day_before = getdate(mktime(0, 0, 0, $start_month, $start_day-1, $start_year));
    $start_day = $day_before['mday'];
    $start_month = $day_before['mon'];
    $start_year = $day_before['year'];
  }
}


// Check that the user has permission to create/edit an entry for this room.
// Get the id of the room that we are creating/editing
if (isset($id))
{
  // Editing an existing booking: get the room_id from the database (you can't
  // get it from $rooms because they are the new rooms)
  $target_room = db()->query1("SELECT room_id FROM $tbl_entry WHERE id=? LIMIT 1", array($id));
  if ($target_room < 0)
  {
    // Ideally we should give more feedback to the user when this happens, or
    // even lock the entry once a user starts to edit it.
    $message = "Tried to edit an entry that no longer exists - probably because " .
               "somebody else has deleted it in the meantime.";
    trigger_error($message, E_USER_NOTICE);
    header("Location: $returl");
    exit;
  }
}
else
{
  // New booking: get the room_id from the form
  if (!isset($rooms[0]))
  {
    // $rooms[0] should always be set, because you can only get here
    // from edit_entry.php, where it will be set.   If it's not set
    // then something's gone wrong - probably somebody trying to call
    // edit_entry_handler.php directly from the browser - so get out 
    // of here and go somewhere safe.
    header("Location: index.php");
    exit;
  }
  $target_room = $rooms[0];
}
if (!getWritable($create_by, $user, $target_room))
{
  showAccessDenied($day, $month, $year, $area, isset($room) ? $room : null);
  exit;
}


if ($enable_periods)
{
  $resolution = 60;
}

// Now work out the start and times
$starttime = mktime(0, 0, $start_seconds, $start_month, $start_day, $start_year);
$endtime   = mktime(0, 0, $end_seconds, $end_month, $end_day, $end_year);

// If we're using periods then the endtime we've been returned by the form is actually
// the beginning of the last period in the booking (it's more intuitive for users this way)
// so we need to add on 60 seconds (1 period)
if ($enable_periods)
{
  $endtime = $endtime + 60;
}

// Round down the starttime and round up the endtime to the nearest slot boundaries
// (This step is probably unnecesary now that MRBS always returns times aligned
// on slot boundaries, but is left in for good measure).
$am7 = get_start_first_slot($start_month, $start_day, $start_year);                 
$starttime = round_t_down($starttime, $resolution, $am7);
$endtime = round_t_up($endtime, $resolution, $am7);

// If they asked for 0 minutes, and even after the rounding the slot length is still
// 0 minutes, push that up to 1 resolution unit.
if ($endtime == $starttime)
{
  $endtime += $resolution;
}

if (isset($rep_type) && ($rep_type != REP_NONE) &&
    isset($rep_end_month) && isset($rep_end_day) && isset($rep_end_year))
{
  // Get the repeat entry settings
  $end_date = mktime(intval($start_seconds/SECONDS_PER_HOUR),
                     intval(($start_seconds%SECONDS_PER_HOUR)/60),
                     0,
                     $rep_end_month, $rep_end_day, $rep_end_year);
}
else
{
  $rep_type = REP_NONE;
  $end_date = 0;  // to avoid an undefined variable notice
}

if (!isset($rep_day))
{
  $rep_day = array();
}

$rep_opt = "";


// Get the repeat details
if (isset($rep_type) && ($rep_type != REP_NONE))
{
  if ($rep_type == REP_WEEKLY)
  {
    // If no repeat day has been set, then set a default repeat day
    // as the day of the week of the start of the period
    if (count($rep_day) == 0)
    {
      $rep_day[] = date('w', $starttime);
    }
    // Build string of weekdays to repeat on:
    for ($i = 0; $i < 7; $i++)
    {
      $rep_opt .= in_array($i, $rep_day) ? "1" : "0";  // $rep_opt is a string
    }
  }
  
  // Make sure that the starttime coincides with a repeat day.  In
  // other words make sure that the first starttime defines an actual
  // entry.   We need to do this because if we are going to construct an iCalendar
  // object, RFC 5545 demands that the start time is the first event of
  // a series.  ["The "DTSTART" property for a "VEVENT" specifies the inclusive
  // start of the event.  For recurring events, it also specifies the very first
  // instance in the recurrence set."]
  
  $rep_details = array('rep_type'      => $rep_type,
                       'rep_opt'       => $rep_opt,
                       'rep_num_weeks' => $rep_num_weeks);
  if (isset($month_type))
  {
    if ($month_type == REP_MONTH_ABSOLUTE)
    {
      $rep_details['month_absolute'] = $month_absolute;
    }
    else
    {
      $rep_details['month_relative'] = $month_relative;
    }
  }

  // Get the first entry in the series and make that the start time
  $reps = mrbsGetRepeatEntryList($starttime, $end_date, $rep_details, 1);

  if (count($reps) > 0)
  {
    $duration = $endtime - $starttime;
    $duration -= cross_dst($starttime, $endtime);
    $starttime = $reps[0];
    $endtime = $starttime + $duration;
    $start_day = date('j', $starttime);
    $start_month = date('n', $starttime);
    $start_year = date('Y', $starttime);
  }
}


if (!$ajax || !$commit)
{
  // Get the start day/month/year and make them the current day/month/year
  $day = $start_day;
  $month = $start_month;
  $year = $start_year;
}

// Set up the return URL.    As the user has tried to book a particular room and a particular
// day, we must consider these to be the new "sticky room" and "sticky day", so modify the 
// return URL accordingly.

// First get the return URL basename, having stripped off the old query string
//   (1) It's possible that $returl could be empty, for example if edit_entry.php had been called
//       direct, perhaps if the user has it set as a bookmark
//   (2) Avoid an endless loop.   It shouldn't happen, but just in case ...
//   (3) If you've come from search, you probably don't want to go back there (and if you did we'd
//       have to preserve the search parameter in the query string)
if (isset($returl) && ($returl !== ''))
{
  $returl = parse_url($returl, PHP_URL_PATH);
  if ($returl !== false)
  {
    $returl = explode('/', $returl);
    $returl = end($returl);
  }
}

if (empty($returl) || 
    in_array($returl, array('edit_entry.php',
                            'edit_entry_handler.php',
                            'search.php')))
{
  switch ($default_view)
  {
    case 'month':
      $returl = 'month.php';
      break;
    case 'week':
      $returl = 'week.php';
      break;
    default:
      $returl = 'day.php';
      break;
  }
}

// If we haven't been given a sensible date then get out of here and don't try and make a booking
if (!isset($start_day) || !isset($start_month) || !isset($start_year) || !checkdate($start_month, $start_day, $start_year))
{
  header("Location: $returl");
  exit;
}

// Now construct the new query string
$returl .= "?year=$year&month=$month&day=$day";

// If the old sticky room is one of the rooms requested for booking, then don't change the sticky room.
// Otherwise change the sticky room to be one of the new rooms.
if (!in_array($room, $rooms))
{
  $room = $rooms[0];
} 
// Find the corresponding area
$area = mrbsGetRoomArea($room);
// Complete the query string
$returl .= "&area=$area&room=$room";


// Check to see whether this is a repeat booking and if so, whether the user
// is allowed to make/edit repeat bookings.   (The edit_entry form should
// prevent you ever getting here, but this check is here as a safeguard in 
// case someone has spoofed the HTML)
if (isset($rep_type) && ($rep_type != REP_NONE) &&
    !$is_admin &&
    !empty($auth['only_admin_can_book_repeat']))
{
  showAccessDenied($day, $month, $year, $area, isset($room) ? $room : null);
  exit;
}


// (4) Assemble the booking data
// -----------------------------

// Assemble an array of bookings, one for each room
$bookings = array();
foreach ($rooms as $room_id)
{
  $booking = array();
  $booking['create_by'] = $create_by;
  $booking['modified_by'] = (isset($id)) ? $user : '';
  $booking['name'] = $name;
  $booking['type'] = $type;
  $booking['description'] = $description;
  $booking['room_id'] = $room_id;
  $booking['start_time'] = $starttime;
  $booking['end_time'] = $endtime;
  $booking['rep_type'] = $rep_type;
  $booking['rep_opt'] = $rep_opt;
  $booking['rep_num_weeks'] = $rep_num_weeks;
  $booking['end_date'] = $end_date;
  $booking['ical_uid'] = $ical_uid;
  $booking['ical_sequence'] = $ical_sequence;
  $booking['ical_recur_id'] = $ical_recur_id;
  if ($booking['rep_type'] == REP_MONTHLY)
  {
    if ($month_type == REP_MONTH_ABSOLUTE)
    {
      $booking['month_absolute'] = $month_absolute;
    }
    else
    {
      $booking['month_relative'] = $month_relative;
    }
  }

  // Do the custom fields
  foreach ($custom_fields as $key => $value)
  {
    $booking[$key] = $value;
  }

  // Set the various bits in the status field as appropriate
  // (Note: the status field is the only one that can differ by room)
  $status = 0;
  // Privacy status
  if ($isprivate)
  {
    $status |= STATUS_PRIVATE;  // Set the private bit
  }
  // If we are using booking approvals then we need to work out whether the
  // status of this booking is approved.   If the user is allowed to approve
  // bookings for this room, then the status will be approved, since they are
  // in effect immediately approving their own booking.  Otherwise the booking
  // will need to approved.
  if ($approval_enabled && !auth_book_admin($user, $room_id))
  {
    $status |= STATUS_AWAITING_APPROVAL;
  }
  // Confirmation status
  if ($confirmation_enabled && !$confirmed)
  {
    $status |= STATUS_TENTATIVE;
  }
  $booking['status'] = $status;
  
  $bookings[] = $booking;
}

$just_check = $ajax && function_exists('json_encode') && !$commit;
$this_id = (isset($id)) ? $id : NULL;
$send_mail = ($no_mail) ? FALSE : $need_to_send_mail;

// Wrap the editing process in a transaction, because if deleting the old booking should fail for
// some reason then we'll potentially be left with two overlapping bookings.  A deletion could fail
// if, for example, the database user hasn't been granted DELETE rights.

// Acquire mutex to lock out others trying to book the same slot(s).
if (!db()->mutex_lock($tbl_entry))
{
  fatal_error(get_vocab("failed_to_acquire"));
}

db()->begin();
  
$transaction_ok = true;

$result = mrbsMakeBookings($bookings, $this_id, $just_check, $skip, $original_room_id, $send_mail, $edit_type);

// If we weren't just checking and this was a succesful booking and
// we were editing an existing booking, then delete the old booking
if (!$just_check && $result['valid_booking'] && isset($id))
{
  $transaction_ok = mrbsDelEntry($user, $id, ($edit_type == "series"), 1);
}

if ($transaction_ok)
{
  db()->commit();
}
else
{
  db()->rollback();
  trigger_error('Edit failed.', E_USER_WARNING);
}

db()->mutex_unlock($tbl_entry);


// If this is an Ajax request, output the result and finish
if ($ajax && function_exists('json_encode'))
{
  // If this was a successful commit generate the new HTML
  if ($result['valid_booking'] && $commit)
  {
    // Generate the new HTML
    require_once "functions_table.inc";
    if ($page == 'day')
    {
      $result['table_innerhtml'] = day_table_innerhtml($day, $month, $year, $room, $area, $timetohighlight);
    }
    else
    {
      $result['table_innerhtml'] = week_table_innerhtml($day, $month, $year, $room, $area, $timetohighlight);
    }
  }
  http_headers(array("Content-Type: application/json"));
  echo json_encode($result);
  exit;
}

// Everything was OK.   Go back to where we came from
if ($result['valid_booking'])
{
  header("Location: $returl");
  exit;
}

else
{
  print_header($day, $month, $year, $area, isset($room) ? $room : null);
    
  echo "<h2>" . get_vocab("sched_conflict") . "</h2>\n";
  if (!empty($result['violations']['errors']))
  {
    echo "<p>\n";
    echo get_vocab("rules_broken") . ":\n";
    echo "</p>\n";
    echo "<ul>\n";
    foreach ($result['violations']['errors'] as $rule)
    {
      echo "<li>$rule</li>\n";
    }
    echo "</ul>\n";
  }
  if (!empty($result['conflicts']))
  {
    echo "<p>\n";
    echo get_vocab("conflict").":\n";
    echo "</p>\n";
    echo "<ul>\n";
    foreach ($result['conflicts'] as $conflict)
    {
      echo "<li>$conflict</li>\n";
    }
    echo "</ul>\n";
  }
}

echo "<div id=\"submit_buttons\">\n";

// Back button
echo "<form method=\"post\" action=\"" . htmlspecialchars($returl) . "\">\n";
echo Form::getTokenHTML() . "\n";
echo "<fieldset><legend></legend>\n";
echo "<input type=\"submit\" value=\"" . get_vocab("back") . "\">\n";
echo "</fieldset>\n";
echo "</form>\n";


// Skip and Book button (to book the entries that don't conflict)
// Only show this button if there were no policies broken and it's a series
if (empty($result['violations']['errors'])  &&
    isset($rep_type) && ($rep_type != REP_NONE))
{
  echo "<form method=\"post\" action=\"" . htmlspecialchars(this_page()) . "\">\n";
  echo Form::getTokenHTML() . "\n";
  echo "<fieldset><legend></legend>\n";
  // Put the booking data in as hidden inputs
  $skip = 1;  // Force a skip next time round
  // First the ordinary fields
  foreach ($formvars as $var => $var_type)
  {
    if ($var_type == 'array')
    {
      // See the comment at the top of the page about array formats
      foreach ($$var as $value)
      {
        if (isset($value))
        {
          echo "<input type=\"hidden\" name=\"${var}[]\" value=\"" . htmlspecialchars($value) . "\">\n";
        }
      }
    }
    elseif (isset($$var))
    {
      echo "<input type=\"hidden\" name=\"$var\" value=\"" . htmlspecialchars($$var) . "\">\n";
    }
  }
  // Then the custom fields
  foreach($fields as $field)
  {
    if (array_key_exists($field['name'], $custom_fields) && isset($custom_fields[$field['name']]))
    {
      echo "<input type=\"hidden\"" .
                  " name=\"" . VAR_PREFIX . $field['name'] . "\"" .
                  " value=\"" . htmlspecialchars($custom_fields[$field['name']]) . "\">\n";
    }
  }
  // Submit button
  echo "<input type=\"submit\"" .
              " value=\"" . get_vocab("skip_and_book") . "\"" .
              " title=\"" . get_vocab("skip_and_book_note") . "\">\n";
  echo "</fieldset>\n";
  echo "</form>\n";
}

echo "</div>\n";

output_trailer();

