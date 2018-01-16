<?php
namespace MRBS;

use MRBS\Form\Form;

/*****************************************************************************\
*                                                                            *
*   File name     edit_users.php                                             *
*                                                                            *
*   Description   Edit the user database                                     *
*                                                                            *
*   Notes         Automatically creates the database if it's not present.    *
*                                                                            *
*                 Designed to be easily extensible:                          *
*                 Adding more fields for each user does not require          *
*                 modifying the editor code. Only to add the fields in       *
*                 the database creation code.                                *
*                                                                            *
*                 An admin rights model is used where the level (an          *
*                 integer between 0 and $max_level) denotes rights:          *
*                      0:  no rights                                         *
*                      1:  an ordinary user                                  *
*                      2+: admins, with increasing rights.   Designed to     *
*                          allow more granularity of admin rights, for       *
*                          example by having booking admins, user admins     *
*                          snd system admins.  (System admins might be       *
*                          necessary in the future if, for example, some     *
*                          parameters currently in the config file are      *
*                          made editable from MRBS)                          *
*                                                                            *
*                 Only admins with at least user editing rights (level >=    *
*                 $min_user_editing_level) can edit other users, and they    *
*                 cannot edit users with a higher level than themselves      *
*                                                                            *
*                 To do:                                                     *
*                     - Localisability                                       *
*                                                                            *
\*****************************************************************************/

require "defaultincludes.inc";

// Get non-standard form variables
$Action = get_form_var('Action', 'string');
$Id = get_form_var('Id', 'int');
$password0 = get_form_var('password0', 'string', null, INPUT_POST);
$password1 = get_form_var('password1', 'string', null, INPUT_POST);
$invalid_email = get_form_var('invalid_email', 'int');
$name_empty = get_form_var('name_empty', 'int');
$name_not_unique = get_form_var('name_not_unique', 'int');
$taken_name = get_form_var('taken_name', 'string');
$pwd_not_match = get_form_var('pwd_not_match', 'string');
$pwd_invalid = get_form_var('pwd_invalid', 'string');
$ajax = get_form_var('ajax', 'int');  // Set if this is an Ajax request
$datatable = get_form_var('datatable', 'int');  // Will only be set if we're using DataTables


// Validates that the password conforms to the password policy
// (Ideally this function should also be matched by client-side
// validation, but unfortunately JavaScript's native support for Unicode
// pattern matching is very limited.   Would need to be implemented using
// an add-in library).
function validate_password($password)
{
  global $pwd_policy;
          
  if (isset($pwd_policy))
  {
    // Set up regular expressions.  Use p{Ll} instead of [a-z] etc.
    // to make sure accented characters are included
    $pattern = array('alpha'   => '/\p{L}/',
                     'lower'   => '/\p{Ll}/',
                     'upper'   => '/\p{Lu}/',
                     'numeric' => '/\p{N}/',
                     'special' => '/[^\p{L}|\p{N}]/');
    // Check for conformance to each rule                 
    foreach($pwd_policy as $rule => $value)
    {
      switch($rule)
      {
        case 'length':
          if (utf8_strlen($password) < $pwd_policy[$rule])
          {
            return FALSE;
          }
          break;
        default:
          // turn on Unicode matching
          $pattern[$rule] .= 'u';

          $n = preg_match_all($pattern[$rule], $password, $matches);
          if (($n === FALSE) || ($n < $pwd_policy[$rule]))
          {
            return FALSE;
          }
          break;
      }
    }
  }
  
  // Everything is OK
  return TRUE;
}


// Get the type that should be used with get_form_var() for
// a field which is a member of the array returned by get_field_info()
function get_form_var_type($field)
{
  // "Level" is an exception because we've forced the value to be a string
  // so that it can be used in an associative aeeay
  if ($field['name'] == 'level')
  {
    return 'string';
  }
  switch($field['nature'])
  {
    case 'character':
      $type = 'string';
      break;
    case 'integer':
      $type = 'int';
      break;
    // We can only really deal with the types above at the moment
    default:
      $type = 'string';
      break;
  }
  return $type;
}


function output_row(&$row)
{
  global $ajax, $json_data;
  global $level, $min_user_editing_level, $user;
  global $fields, $ignore_columns, $select_options;
  
  $values = array();
  
  // First column, which is the name
  $html_name = htmlspecialchars($row['name']);
  // You can only edit a user if you have sufficient admin rights, or else if that user is yourself
  if (($level >= $min_user_editing_level) || (strcasecmp($row['name'], $user) == 0))
  {
    $link = htmlspecialchars(this_page()) . "?Action=Edit&amp;Id=" . $row['id'];
    $values[] = "<a title=\"$html_name\" href=\"$link\">$html_name</a>";
  }
  else
  {
    $values[] = "<span class=\"normal\" title=\"$html_name\">$html_name</span>";
  }
    
  // Other columns
  foreach ($fields as $field)
  {
    $key = $field['name'];
    if (!in_array($key, $ignore_columns))
    {
      $col_value = $row[$key];
      switch($key)
      {
        // special treatment for some fields
        case 'level':
          // the level field contains a code and we want to display a string
          // (but we put the code in a span for sorting)
          $values[] = "<span title=\"$col_value\"></span>" .
                      "<div class=\"string\">" . get_vocab("level_$col_value") . "</div>";
          break;
        case 'email':
          // we don't want to truncate the email address
          $escaped_email = htmlspecialchars($col_value);
          $values[] = "<div class=\"string\">\n" .
                      "<a href=\"mailto:$escaped_email\">$escaped_email</a>\n" .
                      "</div>\n";
          break;
        case 'timestamp':
          // Convert the SQL timestamp into a time value and back into a localised string and
          // put the UNIX timestamp in a span so that the JavaScript can sort it properly.
          $unix_timestamp = strtotime($col_value);
          if ($unix_timestamp === false)
          {
            // To cater for timestamps before the start of the Unix Epoch
            $unix_timestamp = 0;
          }
          $values[] = "<span title=\"$unix_timestamp\"></span>" . time_date_string($unix_timestamp);
          break;
        default:
          // Where there's an associative array of options, display
          // the value rather than the key
          if (isset($select_options["users.$key"]) &&
              is_assoc($select_options["users.$key"]))
          {
            if (isset($select_options["users.$key"][$row[$key]]))
            {
              $col_value = $select_options["users.$key"][$row[$key]];
            }
            else
            {
              $col_value = '';
            }
            $values[] = "<div class=\"string\">" . htmlspecialchars($col_value) . "</div>";
          }
          elseif (($field['nature'] == 'boolean') || 
              (($field['nature'] == 'integer') && isset($field['length']) && ($field['length'] <= 2)) )
          {
            // booleans: represent by a checkmark
            $values[] = (!empty($col_value)) ? "<img src=\"images/check.png\" alt=\"check mark\" width=\"16\" height=\"16\">" : "&nbsp;";
          }
          elseif (($field['nature'] == 'integer') && isset($field['length']) && ($field['length'] > 2))
          {
            // integer values
            $values[] = $col_value;
          }
          else
          {
             // strings
            $values[] = "<div class=\"string\" title=\"" . htmlspecialchars($col_value) . "\">" .
                        htmlspecialchars($col_value) . "</div>";
          }
          break;
      }  // end switch
    }
  }  // end foreach

  if ($ajax)
  {
    $json_data['aaData'][] = $values;
  }
  else
  {
    echo "<tr>\n<td>\n";
    echo implode("</td>\n<td>", $values);
    echo "</td>\n</tr>\n";
  }
}

// Set up for Ajax.   We need to know whether we're capable of dealing with Ajax
// requests, which will only be if (a) the browser is using DataTables and (b)
// we can do JSON encoding.    We also need to initialise the JSON data array.
$ajax_capable = $datatable && function_exists('json_encode');

if ($ajax)
{
  $json_data['aaData'] = array();
}

// Get the information about the fields in the users table
$fields = db()->field_info($tbl_users);

$nusers = db()->query1("SELECT COUNT(*) FROM $tbl_users");

/*---------------------------------------------------------------------------*\
|                         Authenticate the current user                         |
\*---------------------------------------------------------------------------*/

// Check the CSRF token if we're going to be altering the database
if (isset($Action) && in_array($Action, array('Delete', 'Update')))
{
  Form::checkToken();
}

$initial_user_creation = 0;

if ($nusers > 0)
{
  $user = getUserName();
  $level = authGetUserLevel($user);
  // Check the user is authorised for this page
  checkAuthorised();
}
else 
// We've just created the table.   Assume the person doing this IS an administrator
// and then send them through to the screen to add the first user (which we'll force
// to be an admin)
{
  $initial_user_creation = 1;
  if (!isset($Action))   // second time through it will be set to "Update"
  {
    $Action = "Add";
    $Id = -1;
  }
  $level = $max_level;
  $user = "";           // to avoid an undefined variable notice
}


/*---------------------------------------------------------------------------*\
|             Edit a given entry - 1st phase: Get the user input.             |
\*---------------------------------------------------------------------------*/

if (isset($Action) && ( ($Action == "Edit") or ($Action == "Add") ))
{
  
  if ($Id >= 0) /* -1 for new users, or >=0 for existing ones */
  {
    // If it's an existing user then get the data from the database
    $result = db()->query("SELECT * FROM $tbl_users WHERE id=?", array($Id));
    $data = $result->row_keyed(0);
    unset($result);
    // Check that we've got a valid result.   We should do normally, but if somebody alters
    // the id parameter in the query string then we won't.   If the result is invalid, go somewhere
    // safe.
    if (!$data)
    {
      trigger_error("Invalid user id $Id", E_USER_NOTICE);
      header("Location: " . this_page());
      exit;
    }
  }
  if (($Id == -1) || (!$data))
  {
    // Otherwise try and get the data from the query string, and if it's
    // not there set the default to be blank.  (The data will be in the 
    // query string if there was an error on validating the data after it
    // had been submitted.   We want to preserve the user's original values
    // so that they don't have to re-type them).
    foreach ($fields as $field)
    {
      $type = get_form_var_type($field);
      $value = get_form_var($field['name'], $type);
      $data[$field['name']] = (isset($value)) ? $value : "";
    }
  }

  /* First make sure the user is authorized */
  if (!$initial_user_creation && !auth_can_edit_user($user, $data['name']))
  {
    showAccessDenied();
    exit();
  }

  print_header();
  
  if ($initial_user_creation == 1)
  {
    print "<h3>" . get_vocab("no_users_initial") . "</h3>\n";
    print "<p>" . get_vocab("no_users_create_first_admin") . "</p>\n";
  }
  
  print "<div id=\"form_container\">";
  print "<form id=\"form_edit_users\" method=\"post\" action=\"" . htmlspecialchars(this_page()) . "\">\n";
  echo Form::getTokenHTML() . "\n";
  ?>
        <fieldset class="admin">
        <legend><?php echo (($Action == "Edit") ? get_vocab("edit_user") : get_vocab("add_new_user"));?></legend>
        <div id="edit_users_input_container">
          <?php
          // Find out how many admins are left in the table - it's disastrous if the last one is deleted,
          // or admin rights are removed!
          if ($Action == "Edit")
          {
            $n_admins = db()->query1("SELECT COUNT(*) FROM $tbl_users WHERE level=?", array($max_level));
            $editing_last_admin = ($n_admins <= 1) && ($data['level'] == $max_level);
          }
          else
          {
            $editing_last_admin = FALSE;
          }
          
          foreach ($fields as $field)
          {
            $key = $field['name'];
            $params = array('label' => get_loc_field_name($tbl_users, $key),
                            'name'  => VAR_PREFIX . $key,
                            'value' => $data[$key]);
            if (isset($maxlength["users.$key"]))
            {
              $params['maxlength'] = $maxlength["users.$key"];
            }
            // First of all output the input for the field
            // The ID field cannot change; The password field must not be shown.
            switch($key)
            {
              case 'id':
                echo "<input type=\"hidden\" name=\"Id\" value=\"$Id\">\n";
                break;
              case 'password_hash':
                echo "<input type=\"hidden\" name=\"" . $params['name'] ."\" value=\"". htmlspecialchars($params['value']) . "\">\n";
                break;
              case 'timestamp':
                // Don't show timestamp in the form at all
                break;
              default:
                echo "<div>\n";
                $params['disabled'] = ($level < $min_user_editing_level) && in_array($key, $auth['db']['protected_fields']);
                switch($key)
                {
                  case 'level':
                    // Work out whether the level select input should be disabled (NB you can't make a <select> readonly)
                    // We don't want the user to be able to change the level if (a) it's the first user being created or
                    // (b) it's the last admin left or (c) they don't have admin rights
                    $params['disabled'] = $initial_user_creation || $editing_last_admin || $params['disabled'];
                    // Only display options up to and including one's own level (you can't upgrade yourself).
                    // If you're not some kind of admin then the select will also be disabled.
                    // (Note - disabling individual options doesn't work in older browsers, eg IE6)
                    $params['options'] = array();     
                    for ($i=0; $i<=$level; $i++)
                    {
                      $params['options'][$i] = get_vocab("level_$i");
                      // Work out which option should be selected by default:
                      //   if we're editing an existing entry, then it should be the current value;
                      //   if we're adding the very first entry, then it should be an admin;
                      //   if we're adding a subsequent entry, then it should be an ordinary user;
                      if ( (($Action == "Edit")  && ($i == $data[$key])) ||
                           (($Action == "Add") && $initial_user_creation && ($i == $max_level)) ||
                           (($Action == "Add") && !$initial_user_creation && ($i == 1)) )
                      {
                        $params['value'] = $i;
                      }
                    }
                    $params['force_assoc'] = TRUE;
                    generate_select($params);
                    break;
                  case 'name':
                    // you cannot change a username (even your own) unless you have user editing rights
                    $params['disabled'] = ($level < $min_user_editing_level);
                    $params['mandatory'] = TRUE;
                    generate_input($params);
                    break;
                  case 'email':
                    $params['type'] = 'email';
                    $params['attributes'] = 'multiple';
                    generate_input($params);
                    break;
                  default:    
                    // Output a checkbox if it's a boolean or integer <= 2 bytes (which we will
                    // assume are intended to be booleans)
                    if (($field['nature'] == 'boolean') || 
                        (($field['nature'] == 'integer') && isset($field['length']) && ($field['length'] <= 2)) )
                    {
                      generate_checkbox($params);
                    }
                    // Output a textarea if it's a character string longer than the limit for a
                    // text input
                    elseif (($field['nature'] == 'character') && isset($field['length']) && ($field['length'] > $text_input_max))
                    {
                      $params['attributes'] = array('rows="8"', 'cols="40"');
                      generate_textarea($params);   
                    }
                    // Otherwise output a text input
                    else
                    {
                      $params['field'] = "users.$key";
                      generate_input($params);
                    }
                    break;
                } // end switch
                echo "</div>\n";
            } // end switch
            
            
            // Then output any error messages associated with the field
            // except for the password field which is a special case
            switch($key)
            {
              case 'email':
                if (!empty($invalid_email))
                {
                  echo "<p class=\"error\">" . get_vocab('invalid_email') . "</p>\n";
                }
                break;
              case 'name':
                if (!empty($name_not_unique))
                {
                  echo "<p class=\"error\">'" . htmlspecialchars($taken_name) . "' " . get_vocab('name_not_unique') . "<p>\n";
                }
                if (!empty($name_empty))
                {
                  echo "<p class=\"error\">" . get_vocab('name_empty') . "<p>\n";
                }
                break;
            }
                     
          } // end foreach
      
          print "<div><p>" . get_vocab("password_twice") . "...</p></div>\n";

          for ($i=0; $i<2; $i++)
          {
            print "<div>\n";
            print "<label for=\"password$i\">" . get_vocab("users.password") . "</label>\n";
            print "<input type=\"password\" id=\"password$i\" name=\"password$i\" value=\"\">\n";
            print "</div>\n";
          }
          
          // Now do any password error messages
          if (!empty($pwd_not_match))
          {
            echo "<p class=\"error\">" . get_vocab("passwords_not_eq") . "</p>\n";
          }
          if (!empty($pwd_invalid))
          {
            echo "<p class=\"error\">" . get_vocab("password_invalid") . "</p>\n";
            if (isset($pwd_policy))
            {
              echo "<ul class=\"error\">\n";
              foreach ($pwd_policy as $rule => $value)
              {
                echo "<li>$value " . get_vocab("policy_" . $rule) . "</li>\n";
              }
              echo "</ul>\n";
            }
          }
          
          if ($editing_last_admin)
          {
            echo "<p><em>(" . get_vocab("warning_last_admin") . ")</em></p>\n";
          }
          ?>
          <input type="hidden" name="Action" value="Update">    
          <input class="submit default_action" type="submit" value="<?php echo(get_vocab("save")); ?>">
          
        </div>
        </fieldset>
      </form>
      <?php
      /* Administrators get the right to delete users, but only those at the same level as them or lower */
      if (($Id >= 0) && ($level >= $min_user_editing_level) && ($level >= $data['level'])) 
      {
        echo "<form id=\"form_delete_users\" method=\"post\" action=\"" . htmlspecialchars(this_page()) . "\">\n";
        echo Form::getTokenHTML() . "\n";
        echo "<div>\n";
        echo "<input type=\"hidden\" name=\"Action\" value=\"Delete\">\n";
        echo "<input type=\"hidden\" name=\"Id\" value=\"$Id\">\n";
        echo "<input class=\"submit\" type=\"submit\" " . 
              (($editing_last_admin) ? "disabled=\"disabled\"" : "") .
              "value=\"" . get_vocab("delete_user") . "\">\n";
        echo "</div>\n";
        echo "</form>\n";
      }
      // otherwise (ie when adding, or else editing when not an admin) give them a cancel button
      // which takes them back to the user list and does nothing
      else
      {
        echo "<form id=\"form_delete_users\" method=\"post\" action=\"" . htmlspecialchars(this_page()) . "\">\n";
        echo Form::getTokenHTML() . "\n";
        echo "<div>\n";
        echo "<input class=\"submit\" type=\"submit\" value=\"" . get_vocab("back") . "\">\n";
        echo "</div>\n";
        echo "</form>\n";
      }
?>
      </div>
<?php

  // Print footer and exit
  output_trailer();
  exit;
}

/*---------------------------------------------------------------------------*\
|             Edit a given entry - 2nd phase: Update the database.            |
\*---------------------------------------------------------------------------*/

if (isset($Action) && ($Action == "Update"))
{
  // If you haven't got the rights to do this, then exit
  $my_id = db()->query1("SELECT id FROM $tbl_users WHERE name=? LIMIT 1",
                        array(utf8_strtolower($user)));
  if (($level < $min_user_editing_level) && ($Id != $my_id ))
  {
    // It shouldn't normally be possible to get here.
    trigger_error("Attempt made to update a user without sufficient rights.", E_USER_NOTICE);
    header("Location: edit_users.php");
    exit;
  }
  
  // otherwise go ahead and update the database
  else
  {
    $values = array();
    $q_string = ($Id >= 0) ? "Action=Edit" : "Action=Add";
    foreach ($fields as $index => $field)
    {
      $fieldname = $field['name'];
      $type = get_form_var_type($field);
      if ($fieldname == 'id')
      {
        // id: don't need to do anything except add the id to the query string;
        // the field itself is auto-incremented
        $q_string .= "&Id=$Id";
        continue; 
      }

      // first, get all the other form variables and put them into an array, $values, which 
      // we will use for entering into the database assuming we pass validation
      $values[$fieldname] = get_form_var(VAR_PREFIX. $fieldname, $type);
      // Truncate the field to the maximum length as a precaution.
      if (isset($maxlength["users.$fieldname"]))
      {
        $values[$fieldname] = utf8_substr($values[$fieldname], 0, $maxlength["users.$fieldname"]);
      }
      // we will also put the data into a query string which we will use for passing
      // back to this page if we fail validation.   This will enable us to reload the
      // form with the original data so that the user doesn't have to
      // re-enter it.  (Instead of passing the data in a query string we
      // could pass them as session variables, but at the moment MRBS does
      // not rely on PHP sessions).
      switch ($fieldname)
      {
        // some of the fields get special treatment
        case 'name':
          // name: convert it to lower case
          $q_string .= "&$fieldname=" . urlencode($values[$fieldname]);
          $values[$fieldname] = utf8_strtolower($values[$fieldname]);
          break;
        case 'password_hash':
          // password: if the password field is blank it means
          // that the user doesn't want to change the password
          // so don't do anything; otherwise calculate the hash.
          // Note: we don't put the password in the query string
          // for security reasons.
          if ($password0 !== '')
          {
            if (\PasswordCompat\binary\check())
            {
              $hash = password_hash($password0, PASSWORD_DEFAULT);
            }
            else
            {
              $hash = md5($password0);
            }
            $values[$fieldname] = $hash;
          }
          break;
        case 'level':
          // level:  set a safe default (lowest level of access)
          // if there is no value set
          $q_string .= "&$fieldname=" . $values[$fieldname];
          if (!isset($values[$fieldname]))
          {
            $values[$fieldname] = 0;
          }
          // Check that we are not trying to upgrade our level.    This shouldn't be possible
          // but someone might have spoofed the input in the edit form
          if ($values[$fieldname] > $level)
          {
            header("Location: edit_users.php");
            exit;
          }
          break;
        case 'timestamp':
          // Don't update this field ourselves at all
          unset($fields[$index]);
          unset($values[$fieldname]);
          break;
        default:
          $q_string .= "&$fieldname=" . urlencode($values[$fieldname]);
          break;
      }
    }

    // Now do some form validation
    $valid_data = TRUE;
    foreach ($values as $fieldname => $value)
    {
      switch ($fieldname)
      {
        case 'name':
          // check that the name is not empty
          if ($value === '')
          {
            $valid_data = FALSE;
            $q_string .= "&name_empty=1";
          }

          $sql_params = array();

          // Check that the name is unique.
          // If it's a new user, then to check to see if there are any rows with that name.
          // If it's an update, then check to see if there are any rows with that name, except
          // for that user.
          $query = "SELECT id FROM $tbl_users WHERE name=?";
          $sql_params[] = $value;
          if ($Id >= 0)
          {
            $query .= " AND id != ?";
            $sql_params[] = $Id;
          }
          $query .= " LIMIT 1";  // we only want to know if there is at least one instance of the name
          $result = db()->query($query, $sql_params);
          if ($result->count() > 0)
          {
            $valid_data = FALSE;
            $q_string .= "&name_not_unique=1";
            $q_string .= "&taken_name=$value";
          }
          break;
        case 'password_hash':
          // check that the two passwords match
          if ($password0 != $password1)
          {
            $valid_data = FALSE;
            $q_string .= "&pwd_not_match=1";
          }
          // check that the password conforms to the password policy
          // if it's a new user (Id < 0), or else it's an existing user
          // trying to change their password
          if (($Id <0) || ($password0 !== ''))
          {
            if (!validate_password($password0))
            {
              $valid_data = FALSE;
              $q_string .= "&pwd_invalid=1";
            }
          }
          break;
        case 'email':
          // check that the email address is valid
          if (isset($value) && ($value !== '') && !validate_email_list($value))
          {
            $valid_data = FALSE;
            $q_string .= "&invalid_email=1";
          }
          break;
      }
    }

    // if validation failed, go back to this page with the query 
    // string, which by now has both the error codes and the original
    // form values 
    if (!$valid_data)
    { 
      header("Location: edit_users.php?$q_string");
      exit;
    }

    
    // If we got here, then we've passed validation and we need to
    // enter the data into the database

    $sql_params = array();
    $sql_fields = array();
  
    // For each db column get the value ready for the database
    foreach ($fields as $field)
    {
      // Stop ordinary users trying to change fields they are not allowed to
      if (($level < $min_user_editing_level) && in_array($field['name'], $auth['db']['protected_fields']))
      {
        continue;
      }
      
      $fieldname = $field['name'];
      if ($fieldname != 'id')
      {
        // pre-process the field value for SQL
        $value = $values[$fieldname];
        switch ($field['nature'])
        {
          case 'integer':
            if (!isset($value) || ($value === ''))
            {
              // Try and set it to NULL when we can because there will be cases when we
              // want to distinguish between NULL and 0 - especially when the field
              // is a genuine integer.
              $value = ($field['is_nullable']) ? null : 0;
            }
            break;
          default:
            // No special handling
            break;
        }
       
        /* If we got here, we have a valid, sql-ified value for this field,
         * so save it for later */
        $sql_fields[$fieldname] = $value;
      }                   
    } /* end for each column of user database */
  
    /* Now generate the SQL operation based on the given array of fields */
    if ($Id >= 0)
    {
      /* if the Id exists - then we are editing an existing user, rather th
       * creating a new one */
  
      $assign_array = array();
      $operation = "UPDATE $tbl_users SET ";
  
      foreach ($sql_fields as $fieldname => $value)
      {
        array_push($assign_array, db()->quote($fieldname) . "=?");
        $sql_params[] = $value;
      }
      $operation .= implode(",", $assign_array) . " WHERE id=?";
      $sql_params[] = $Id;
    }
    else
    {
      /* The id field doesn't exist, so we're adding a new user */
  
      $fields_list = array();
      $values_list = array();
  
      foreach ($sql_fields as $fieldname => $value)
      {
        array_push($fields_list,$fieldname);
        array_push($values_list,'?');
        $sql_params[] = $value;
      }

      foreach ($fields_list as &$field)
      {
        $field = db()->quote($field);
      }
      $operation = "INSERT INTO $tbl_users " .
        "(". implode(",", $fields_list) . ")" .
        " VALUES " . "(" . implode(",", $values_list) . ")";
    }
  
    /* DEBUG lines - check the actual sql statement going into the db */
    //echo "Final SQL string: <code>" . htmlspecialchars($operation) . "</code>";
    //exit;
    db()->command($operation, $sql_params);
  
    /* Success. Redirect to the user list, to remove the form args */
    header("Location: edit_users.php");
  }
}

/*---------------------------------------------------------------------------*\
|                                Delete a user                                |
\*---------------------------------------------------------------------------*/

if (isset($Action) && ($Action == "Delete"))
{
  $target_level = db()->query1("SELECT level FROM $tbl_users WHERE id=? LIMIT 1", array($Id));
  if ($target_level < 0)
  {
    fatal_error("Fatal error while deleting a user");
  }
  // you can't delete a user if you're not some kind of admin, and then you can't
  // delete someone higher than you
  if (($level < $min_user_editing_level) || ($level < $target_level))
  {
    showAccessDenied();
    exit();
  }

  db()->command("DELETE FROM $tbl_users WHERE id=?", array($Id));

  /* Success. Do not display a message. Simply fall through into the list display. */
}

/*---------------------------------------------------------------------------*\
|                          Display the list of users                          |
\*---------------------------------------------------------------------------*/

/* Print the standard MRBS header */

if (!$ajax)
{
  print_header();

  echo "<h2>" . get_vocab("user_list") . "</h2>\n";

  if ($level >= $min_user_editing_level) /* Administrators get the right to add new users */
  {
    echo "<form id=\"add_new_user\" method=\"post\" action=\"" . htmlspecialchars(this_page()) . "\">\n";
    echo Form::getTokenHTML() . "\n";
    echo "  <div>\n";
    echo "    <input type=\"hidden\" name=\"Action\" value=\"Add\">\n";
    echo "    <input type=\"hidden\" name=\"Id\" value=\"-1\">\n";
    echo "    <input type=\"submit\" value=\"" . get_vocab("add_new_user") . "\">\n";
    echo "  </div>\n";
    echo "</form>\n";
  }
}

if ($initial_user_creation != 1)   // don't print the user table if there are no users
{
  // Get the user information
  $res = db()->query("SELECT * FROM $tbl_users ORDER BY level DESC, name");
  
  // Display the data in a table
  $ignore_columns = array('id', 'password_hash', 'name'); // We don't display these columns or they get special treatment
  
  if (!$ajax)
  {
    echo "<div id=\"user_list\" class=\"datatable_container\">\n";
    echo "<table class=\"admin_table display\" id=\"users_table\">\n";
  
    // The table header
    echo "<thead>\n";
    echo "<tr>";
  
    // First column which is the name
    echo "<th>" . get_vocab("users.name") . "</th>\n";
  
    // Other column headers
    foreach ($fields as $field)
    {
      $fieldname = $field['name'];
    
      if (!in_array($fieldname, $ignore_columns))
      {
        $heading = get_loc_field_name($tbl_users, $fieldname);
        // We give some columns a type data value so that the JavaScript knows how to sort them
        switch ($fieldname)
        {
          case 'level':
          case 'timestamp':
            $heading = '<span class="normal" data-type="title-numeric">' . $heading . '</span>';
            break;
          default:
            break;
        }
        echo "<th>$heading</th>";
      }
    }
  
    echo "</tr>\n";
    echo "</thead>\n";
  
    // The table body
    echo "<tbody>\n";
  }
  
  // If we're Ajax capable and this is not an Ajax request then don't output
  // the table body, because that's going to be sent later in response to
  // an Ajax request
  if (!$ajax_capable || $ajax)
  {
    for ($i = 0; ($row = $res->row_keyed($i)); $i++)
    {
      // You can only see this row if (a) we allow everybody to see all rows or
      // (b) you are an admin or (c) you are this user
      if (!$auth['only_admin_can_see_other_users'] ||
          ($level >= $min_user_viewing_level) ||
          (strcasecmp($row['name'], $user) == 0))
      {
        output_row($row);
      }
    }
  }
  
  if (!$ajax)
  {
    echo "</tbody>\n";
  
    echo "</table>\n";
    echo "</div>\n";
  }
  
}   // ($initial_user_creation != 1)

if ($ajax)
{
  http_headers(array("Content-Type: application/json"));
  echo json_encode($json_data);
}
else
{
  output_trailer();
}

