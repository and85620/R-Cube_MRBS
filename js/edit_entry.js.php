<?php
namespace MRBS;

require "../defaultincludes.inc";

http_headers(array("Content-type: application/x-javascript"),
             60*30);  // 30 minute expiry

if ($use_strict)
{
  echo "'use strict';\n";
}

$user = getUserName();
$is_admin = (authGetUserLevel($user) >= $max_level);


// Set (if set is true) or clear (if set is false) a timer
// to check for conflicts periodically in case someone else
// books the slot you are looking at.  If setting the timer
// it also performs an immediate check.
?>
var conflictTimer = function conflictTimer(set) {
    <?php
    if (function_exists('json_encode') &&
        !empty($ajax_refresh_rate))
    {
      ?>
      if (set)
      {
        <?php
        // (Note the config variable is in seconds, but the setInterval() function
        // uses milliseconds)
        // Only set the timer if the page is visible
        ?>
        if (!isHidden())
        {
          checkConflicts(true);
          conflictTimer.id = window.setInterval(function() {
              checkConflicts(true);
            }, <?php echo $ajax_refresh_rate * 1000 ?>);
        }
      }
      else if (typeof conflictTimer.id !== 'undefined')
      {
        window.clearInterval(conflictTimer.id);
      }
      <?php
    }
    ?>
  };


<?php
// Function to display the secondary repeat type fieldset appropriate
// to the selected repeat type
?>
var changeRepTypeDetails = function changeRepTypeDetails() {
    var repType = parseInt($('input[name="rep_type"]:checked').val(), 10);
    $('.rep_type_details').hide();
    switch (repType)
    {
      case <?php echo REP_WEEKLY ?>:
        $('#rep_weekly').show();
        break;
      case <?php echo REP_MONTHLY ?>:
        $('#rep_monthly').show();
        break;
      default:
        break;
    }
  };


// areaConfig returns the properties ('enable_periods', etc.) for an area,
// by default the current area
var areaConfig = function areaConfig(property, areaId) {

    var properties = ['enable_periods', 'n_periods', 'default_duration', 'max_duration_enabled',
                      'max_duration_secs', 'max_duration_periods', 'max_duration_qty',
                      'max_duration_units', 'timezone'];
    var i, p, room;

    if ($.inArray(property, properties) < 0)
    {
      throw new Error("areaConfig(): invalid property '" + property + "' passed to areaConfig");
    }
    
    if (areaId === undefined)
    {
      areaId = $('#area').val();
    }
    
    if (areaConfig.data === undefined)
    {
      areaConfig.data = [];
    }
    if (areaConfig.data[areaId] === undefined)
    {
      areaConfig.data[areaId] = {};
      room = $('#rooms' + areaId);
      for (i=0; i<properties.length; i++)
      {
        p = properties[i];
        areaConfig.data[areaId][p] = room.data(p);
      }
    }
    return areaConfig.data[areaId][property];
  };
  

<?php
// Check to see whether any time slots should be removed from the time
// select on the grounds that they don't exist due to a transition into DST.
// Don't do this if we're using periods, because it doesn't apply then
//
//    jqDate          a jQuery object for the datepicker in question
?>
function checkTimeSlots(jqDate)
{
  <?php
  // Only do something if we can return a JSON result
  if (function_exists('json_encode'))
  {
    ?>
    if (!areaConfig('enable_periods'))
    {
      var siblings = jqDate.siblings();
      var select = jqDate.parent().parent().siblings('select:visible');
      var slots = [];
      select.find('option').each(function() {
          slots.push($(this).val());
        });
      <?php
      // We pass the id of the element as the request id so that we can match
      // the result to the request
      ?>
      var params = {csrf_token: getCSRFToken(),
                    id: select.attr('id'),
                    day: parseInt(siblings.filter('input[id*="day"]').val(), 10),
                    month: parseInt(siblings.filter('input[id*="month"]').val(), 10),
                    year: parseInt(siblings.filter('input[id*="year"]').val(), 10),
                    tz: areaConfig('timezone'),
                    slots: slots};
      $.post('check_slot_ajax.php', params, function(result) {
          $.each(result.slots, function(key, value) {
              $('#' + result.id).find('option[value="' + value + '"]').remove();
            });
          <?php
          // Now that we've removed some options we need to equalise the widths
          ?>
          adjustWidth($('#start_seconds'),
                      $('#end_seconds'));
        }, 'json');
    } <?php // if (!areaConfig('enable_periods'))
  } // if (function_exists('json_encode'))
  ?>
}
  
  
<?php
// Executed when the user clicks on the all_day checkbox.
?>
function onAllDayClick()
{
  var form = $('#main');
  if (form.length === 0)
  {
    return;
  }

  var startSelect = form.find('#start_seconds'),
      endSelect = form.find('#end_seconds'),
      allDay = form.find('#all_day');
      
  var startDatepicker = form.find('#start_datepicker'),
      endDatepicker = form.find('#end_datepicker');
  
  var date, firstSlot, lastSlot;

  if (allDay.is(':checked')) // If checking the box...
  {
    <?php
    // Save the old values, disable the inputs and, to avoid user confusion,
    // show the start and end times as the beginning and end of the booking
    ?>
    firstSlot = parseInt(startSelect.find('option').first().val(), 10);
    lastSlot = parseInt(endSelect.find('option').last().val(), 10);
    onAllDayClick.oldStart = parseInt(startSelect.val(), 10);
    onAllDayClick.oldStartDatepicker = startDatepicker.datepicker('getDate');
    startSelect.val(firstSlot);
    startSelect.prop('disabled', true);
    onAllDayClick.oldEnd = parseInt(endSelect.val(), 10);
    onAllDayClick.oldEndDatepicker = endDatepicker.datepicker('getDate');
    endSelect.val(lastSlot);
    if ((lastSlot < firstSlot) && 
        (onAllDayClick.oldStartDatepicker === onAllDayClick.oldEndDatepicker))
    {
      <?php
      // If the booking day spans midnight then the first and last slots
      // are going to be on different days
      ?>
      if (onAllDayClick.oldStart < firstSlot)
      {
        date = new Date(onAllDayClick.oldStartDatepicker);
        date.setDate(date.getDate() - 1);
        startDatepicker.datepicker('setDate', date);
      }
      else
      {
        date = new Date(onAllDayClick.oldEndDatepicker);
        date.setDate(date.getDate() + 1);
        endDatepicker.datepicker('setDate', date);
      }
    }
    endSelect.prop('disabled', true);
  }
  else  <?php // restore the old values and re-enable the inputs ?>
  {
    startSelect.val(onAllDayClick.oldStart);
    startDatepicker.datepicker('setDate', onAllDayClick.oldStartDatepicker);
    startSelect.prop('disabled', false);
    endSelect.val(onAllDayClick.oldEnd);
    endDatepicker.datepicker('setDate', onAllDayClick.oldEndDatepicker);
    endSelect.prop('disabled', false);
  }

  adjustSlotSelectors(); <?php // need to get the duration right ?>

}

<?php
// Set the error messages to be used for the various fields.     We do this twice:
// once to redefine the HTML5 error message and once for JavaScript alerts, for those
// browsers not supporting HTML5 field validation.
?>
function validationMessages()
{
  var field, label;
  <?php
  // First of all create a property in the vocab object for each of the mandatory
  // fields.    The name and rooms field are implicitly mandatory.
  ?>
  validationMessages.vocab = {};
  validationMessages.vocab['name'] = '';
  validationMessages.vocab['rooms'] = '';
  <?php
  foreach ($is_mandatory_field as $key => $value)
  {
    list($table, $fieldname) = explode('.', $key, 2);
    if ($table == 'entry')
    {
      $prefix = (in_array($fieldname, $standard_fields['entry'])) ? '' : VAR_PREFIX;
      ?>
      validationMessages.vocab['<?php echo escape_js($prefix . $fieldname) ?>'] = '';
      <?php
    }
  }

  // Then (a) fill each of those properties with an error message and (b) redefine
  // the HTML5 error message
  ?>
  for (var key in validationMessages.vocab)
  {
    if (validationMessages.vocab.hasOwnProperty(key))
    {
      label = $("label[for=" + key + "]");
      if (label.length > 0)
      {
        validationMessages.vocab[key] = label.text();
        validationMessages.vocab[key] = '"' + validationMessages.vocab[key] + '" ';
        validationMessages.vocab[key] += '<?php echo escape_js(get_vocab("is_mandatory_field")) ?>';
    
        field = document.getElementById(key);
        if (field.setCustomValidity && field.willValidate)
        {
          <?php
          // We define our own custom event called 'validate' that is triggered on the
          // 'change' event for checkboxes and select elements, and the 'input' even
          // for all others.   We cannot use the change event for text input because the
          // change event is only triggered when the element loses focus and we want the
          // validation to happen whenever a character is input.   And we cannot use the
          // 'input' event for checkboxes or select elements because it is not triggered
          // on them.
          ?>
          $(field).on('validate', function(e) {
            <?php
            // need to clear the custom error message otherwise the browser will
            // assume the field is invalid
            ?>
            e.target.setCustomValidity("");
            if (!e.target.validity.valid)
            {
              e.target.setCustomValidity(validationMessages.vocab[$(e.target).attr('id')]);
            }
          });
          $(field).filter('select, [type="checkbox"]').on('change', function() {
            $(this).trigger('validate');
          });
          $(field).not('select, [type="checkbox"]').on('input', function() {
            $(this).trigger('validate');
          });
          <?php
          // When a form validation fails we need to clear the submit flag because
          // otherwise checkConflicts() won't do anything (because we don't check
          // for conflicts on a submit)
          ?>
          $(field).on('invalid', function() {
            $(this).closest('form').removeData('submit');
          });
          <?php
          // Trigger the validate event when the form is first loaded
          ?>
          $(field).trigger('validate');
        }
      }  <?php // if (label.length > 0) ?>
    }  <?php // if (validationMessages.vocab.hasOwnProperty(key)) ?>
  }  <?php //for ?>
}


<?php
// do a little form verifying
?>
function validate(form)
{
  var testInput = document.createElement("input");
  var testSelect = document.createElement("select");
  var validForm = true;
  
  <?php
  // Mandatory fields (INPUT elements, except for checkboxes).
  // Only necessary if the browser doesn't support the HTML5 pattern or
  // required attributes
  ?>
  if (!("pattern" in testInput) || !("required" in testInput))
  {
    form.find('input').not('[type="checkbox"]').each(function() {
      var id = $(this).attr('id');
      if (validationMessages.vocab[id])
      {
        if (<?php echo REGEX_TEXT_NEG ?>.test($(this).val()))
        {
          window.alert(validationMessages.vocab[id]);
          validForm = false;
          return false;
        }
      }
    });
    if (!validForm)
    {
      return false;
    }
  }
  
  <?php
  // Mandatory fields (INPUT elements, checkboxes only).
  // Only necessary if the browser doesn't support the HTML5 required attribute
  ?>
  if (!("required" in testInput))
  {
    form.find('input').filter('[type="checkbox"]').each(function() {
      var id = $(this).attr('id');
      if (validationMessages.vocab[id])
      {
        if (!$(this).is(':checked'))
        {
          window.alert(validationMessages.vocab[id]);
          validForm = false;
          return false;
        }
      }
    });
    if (!validForm)
    {
      return false;
    }
  }
  
  <?php
  // Mandatory fields (TEXTAREA elements).
  // Note that the TEXTAREA element only supports the "required" attribute and not
  // the "pattern" attribute.    So we need to do these tests in all cases because
  // the browser will let through a string consisting only of whitespace.
  ?>
  form.find('textarea').each(function() {
    var id = $(this).attr('id');
    if (validationMessages.vocab[id])
    {
      if (<?php echo REGEX_TEXT_NEG ?>.test($(this).val()))
      {
        window.alert(validationMessages.vocab[id]);
        validForm = false;
        return false;
      }
    }
  });
  if (!validForm)
  {
    return false;
  }
  
  <?php
  // Mandatory fields (SELECT elements).
  // Only necessary if the browser doesn't support the HTML5 required attribute
  ?>
  if (!("required" in testSelect))
  {
    form.find('select').each(function() {
      var id = $(this).attr('id');
      if (validationMessages.vocab[id])
      {
        if ($(this).val() === '')
        {
          window.alert(validationMessages.vocab[id]);
          validForm = false;
          return false;
        }
      }
    });
    if (!validForm)
    {
      return false;
    }
  }
  
  <?php // Check that the start date is not after the end date ?>
  var dateDiff = getDateDifference();
  if (dateDiff < 0)
  {
    window.alert("<?php echo escape_js(get_vocab('start_after_end_long'))?>");
    return false;
  }
  
  <?php
  // Check that there's a sensible value for rep_num_weeks.   Only necessary
  // if the browser doesn't support the HTML5 min and step attrubutes
  ?>
  if (!("min" in testInput) || !(("step" in testInput)))
  {
    if ((form.find('input:radio[name=rep_type]:checked').val() === '<?php echo REP_WEEKLY ?>') &&
        (form.find('#rep_num_weeks').val() < <?php echo REP_NUM_WEEKS_MIN ?>))
    {
      window.alert("<?php echo escape_js(get_vocab('you_have_not_entered')) . '\n' . escape_js(get_vocab('useful_n-weekly_value')) ?>");
      return false;
    }
  }
    
  <?php
  // Form submit can take some time, especially if mails are enabled and
  // there are more than one recipient. To avoid users doing weird things
  // like clicking more than one time on submit button, we hide it as soon
  // it is clicked.
  ?>
  form.find('input[type=submit]').prop('disabled', true);
  
  <?php
  // would be nice to also check date to not allow Feb 31, etc...
  ?>
  
  return true;
}


<?php
// function to check whether the proposed booking would (a) conflict with any other bookings
// and (b) conforms to the booking policies.   Makes an Ajax call to edit_entry_handler but does
// not actually make the booking.
//
// If optional is true then the check is not carried out if there's already an
// outstanding request in the queue
?>
function checkConflicts(optional)
{
  <?php
  // Only do something if we can the result as a JSON object
  if (function_exists('json_encode'))
  {
    // Get the value of the field in the form
    ?>
    function getFormValue(formInput)
    {
      var value;
      <?php 
      // Scalar parameters (three types - checkboxes, radio buttons and the rest)
      ?>
      if (formInput.attr('name').indexOf('[]') === -1)
      {
        if (formInput.filter(':checkbox').length > 0)
        {
          value = formInput.is(':checked') ? '1' : '';
        }
        else if (formInput.filter(':radio').length > 0)
        {
          value = formInput.filter(':checked').val();
        }
        else
        {
          value = formInput.val();
        }
      }
      <?php
      // Array parameters (two types - checkboxes and the rest, which could be
      // <select> elements or else multiple ordinary inputs with a *[] name
      ?>
      else
      {
        value = [];
        formInput.each(function() {
            if ((formInput.filter(':checkbox').length === 0) || $(this).is(':checked'))
            {
              var thisValue = $(this).val();
              if ($.isArray(thisValue))
              {
                $.merge(value, thisValue);
              }
              else
              {
                value.push($(this).val());
              }
            }
          });
      }
      return value;
    } <?php // function getFormValue()


    // Keep track of how many requests are still with the server.   We don't want
    // to keep sending them if they're not coming back
    ?>
    if (checkConflicts.nOutstanding === undefined)
    {
      checkConflicts.nOutstanding = 0;
    }
    <?php
    // If this is an optional request and there are already some check requests
    // in the queue, then don't bother with this one.
    ?>
    if (optional && checkConflicts.nOutstanding)
    {
      return;
    }
    
    <?php
    // We set a small timeout on checking the booking in order to allow time for
    // the click handler on the Submit buttons to set the data in the form.  We then
    // test the data and if it is set we don't validate the booking because we're going off
    // somewhere else.  [This isn't an ideal way of doing this.   The problem is that
    // the change event for a text input can be fired when the user clicks the submit
    // button - but how can you tell that it was the clicking of the submit button that
    // caused the change event?]
    ?>
    var timeout = 200; <?php // ms ?>
    window.setTimeout(function() {
      var params = {'ajax': 1}; <?php // This is an Ajax request ?>
      var form = $('form#main');
      <?php
      // Don't do anything if (a) the form doesn't exist (which it won't if the user
      // hasn't logged in) or (b) if the submit button has been pressed
      ?>
      if ((form.length === 0) || form.data('submit'))
      {
        return;
      }
      
      <?php
      // Load the params object with the values of all the form fields that are not
      // disabled and are not submit buttons of one kind or another
      ?>
      var relevantFields = form.find('[name]').not(':disabled, [type="submit"], [type="button"], [type="image"]');
      relevantFields.each(function() {
          <?php
          // Go through each of the fields and if we haven't got the value for a name
          // then go and get it.  (Remember that arrays can give more than one field
          // with the same name
          ?>
          var fieldName = $(this).attr('name');
          if (params[fieldName] === undefined)
          {
            params[fieldName] = getFormValue(relevantFields.filter('[name=' + fieldName.replace('[', '\\[').replace(']', '\\]') + ']'));
          }
        });
        
      <?php
      // For some reason I don't understand, posting an empty array will
      // give you a PHP array of ('') at the other end.    So to avoid
      // that problem, delete the property if the array (really an object) is empty
      ?>
      $.each(params, function(i, val) {
          if ((typeof(val) === 'object') && ((val === null) || (val.length === 0)))
          {
            delete params[i];
          }
        });
      
      checkConflicts.nOutstanding++; 
      $.post('edit_entry_handler.php', params, function(result) {
          if (result)
          {
            checkConflicts.nOutstanding--;
            var conflictDiv = $('#conflict_check');
            var scheduleDetails = $('#schedule_details');
            var policyDetails = $('#policy_details');
            var titleText, detailsHTML;
            if (result.conflicts.length === 0)
            {
              conflictDiv.attr('class', 'good');
              titleText = '<?php echo escape_js(html_entity_decode(get_vocab("no_conflicts"))) ?>';
              detailsHTML = titleText;
            }
            else
            {
              conflictDiv.attr('class', 'bad');
              detailsHTML = "<p>";
              titleText = '<?php echo escape_js(html_entity_decode(get_vocab("conflict"))) ?>' + "\n\n";
              detailsHTML += titleText + "<\/p>";
              var conflictsList = getErrorList(result.conflicts);
              detailsHTML += conflictsList.html;
              titleText += conflictsList.text;
            }
            conflictDiv.attr('title', titleText);
            scheduleDetails.html(detailsHTML);
            
            <?php
            // Display the results of the policy check.   Set the class to "good" if there
            // are no policy violations at all.  To "notice" if there are no errors, but some
            // notices (this happens when an admin user makes a booking that an ordinary user
            // would not be allowed to.  Otherwise "bad".  Content and styling are supplied by CSS.
            ?>
            var policyDiv = $('#policy_check');
            if (result.violations.errors.length === 0)
            {
              if (result.violations.notices.length === 0)
              {
                policyDiv.attr('class', 'good');
                titleText = '<?php echo escape_js(html_entity_decode(get_vocab("no_rules_broken"))) ?>';
                detailsHTML = titleText;
              }
              else
              {
                policyDiv.attr('class', 'notice');
                detailsHTML = "<p>";
                titleText = '<?php echo escape_js(html_entity_decode(get_vocab("rules_broken_notices"))) ?>' + "\n\n";
                detailsHTML += titleText + "<\/p>";
                var rulesList = getErrorList(result.violations.notices);
                detailsHTML += rulesList.html;
                titleText += rulesList.text;
              }
            }
            else
            {
              policyDiv.attr('class', 'bad');
              detailsHTML = "<p>";
              titleText = '<?php echo escape_js(html_entity_decode(get_vocab("rules_broken"))) ?>' + "\n\n";
              detailsHTML += titleText + "<\/p>";
              var rulesList = getErrorList(result.violations.errors);
              detailsHTML += rulesList.html;
              titleText += rulesList.text;
            }
            policyDiv.attr('title', titleText);
            policyDetails.html(detailsHTML);
          }  <?php // if (result) ?>
        }, 'json');
    }, timeout);  <?php // setTimeout()
  } // if (function_exists('json_encode')) ?>
  
} <?php // function checkConflicts()


// Get the current vocab (in the appropriate language) for periods,
// minutes, hours and days
?>
var vocab = {};
vocab.periods = {singular: '<?php echo escape_js(get_vocab("period_lc")) ?>',
                 plural:   '<?php echo escape_js(get_vocab("periods")) ?>'};
vocab.minutes = {singular: '<?php echo escape_js(get_vocab("minute_lc")) ?>',
                 plural:   '<?php echo escape_js(get_vocab("minutes")) ?>'};
vocab.hours   = {singular: '<?php echo escape_js(get_vocab("hour_lc")) ?>',
                 plural:   '<?php echo escape_js(get_vocab("hours")) ?>'};
vocab.days    = {singular: '<?php echo escape_js(get_vocab("day_lc")) ?>',
                 plural:   '<?php echo escape_js(get_vocab("days")) ?>'};


function durFormat(r)
{
  r = r.toFixed(2);
  r = parseFloat(r);
  r = r.toLocaleString();

  if ((r.indexOf('.') >= 0) || (r.indexOf(',') >= 0))
  {
    while (r.substr(r.length -1) === '0')
    {
      r = r.substr(0, r.length - 1);
    }

    if ((r.substr(r.length -1) === '.') || (r.substr(r.length -1) === ','))
    {
      r = r.substr(0, r.length - 1);
    }
  }
    
  return r;
}
  
<?php
// Returns a string giving the duration having chosen sensible units,
// translated into the user's language, and formatted the number, taking
// into account the user's locale.    Note that when using periods one
// is added to the duration because the model is slightly different
//   - from   the start time (in seconds since the start of the day
//   - to     the end time (in seconds since the start of the day)
//   - days   the number of days difference
?>
function getDuration(from, to, days)
{
  var duration, durUnits;
  var text = '';
  var currentArea = $('#area').data('current');
  var enablePeriods = areaConfig('enable_periods');
  var durDays;
  var minutesPerDay = <?php echo MINUTES_PER_DAY ?>;

  
  durUnits = (enablePeriods) ? '<?php echo "periods" ?>' : '<?php echo "minutes" ?>';
  duration = to - from;
  duration = Math.floor((to - from) / 60);
  
  if (enablePeriods)
  {
    duration++;  <?php // a period is a period rather than a point ?>
  }
  
  <?php
  // Adjust the days and duration so that 0 <= duration < minutesPerDay.    If we're using
  // periods then if necessary add/subtract multiples of the number of periods in a day
  ?>
  durDays = Math.floor(duration/minutesPerDay);
  if (durDays !== 0)
  {
    days += durDays;
    duration -= durDays * ((enablePeriods) ? $('#start_seconds' + currentArea).find('option').length : minutesPerDay);
  }
  
  if (!enablePeriods && (duration >= 60))
  {
    durUnits = "hours";
    duration = durFormat(duration/60);
  }

  <?php
  // As durFormat returns a string, duration can now be either
  // a number or a string, so convert it to a string so that we
  // know what we are dealing with
  ?>
  duration = duration.toString();
  
  if (days !== 0)
  {
    text += days + ' ';
    text += (days === 1) ? vocab.days.singular : vocab.days.plural;
    if (duration !== '0')
    {
      text +=  ', ';
    }
  }

  if (duration !== '0')
  {
    text += duration + ' ';
    text += (duration === '1') ? vocab[durUnits].singular : vocab[durUnits].plural;
  }

  return text;
}
  
<?php
// Returns the number of days between the start and end dates
?>
function getDateDifference()
{
  var diff,
      secondsPerDay = <?php echo SECONDS_PER_DAY ?>,
      start = $('#start_datepicker_alt').val().split('-'),
      startDate = new Date(parseInt(start[0], 10), 
                           parseInt(start[1], 10) - 1,
                           parseInt(start[2], 10),
                           12),
      endDatepickerAlt = $('#end_datepicker_alt'),
      end,
      endDate;
      
  if (endDatepickerAlt.length === 0)
  {
    <?php
    // No end date selector, so assume the end date is
    // the same as the start date
    ?>
    diff = 0;
  }
  else
  {
    end = $('#end_datepicker_alt').val().split('-'); 
    endDate = new Date(parseInt(end[0], 10), 
                       parseInt(end[1], 10) - 1,
                       parseInt(end[2], 10),
                       12);

    diff = (endDate - startDate)/(secondsPerDay * 1000);
    diff = Math.round(diff);
  }
    
  return diff;
}
  

<?php
// Make two jQuery objects the same width.
?>
function adjustWidth(a, b)
{
  <?php 
  // Note that we set the widths of both objects, even though it would seem
  // that just setting the width of the smaller should be sufficient.
  // But if you don't set both of them then you end up with a few 
  // pixels difference.  In other words doing a get and then a set 
  // doesn't leave you where you started - not quite sure why.
  // The + 2 is a fudge factor to make sure that the option text in select
  // elements isn't truncated - not quite sure why it is necessary.
  // The width: auto is necessary to get the elements to resize themselves
  // according to their new contents.
  ?>
  a.css({width: "auto"});
  b.css({width: "auto"});
  var aWidth = a.width();
  var bWidth = b.width();
  var maxWidth = Math.max(aWidth, bWidth) + 2;
  a.width(maxWidth);
  b.width(maxWidth);
}
  
  
var reloadSlotSelector = function reloadSlotSelector(select, area) {
    select.html($('#' + select.attr('id') + area).html())
          .val(select.data('current'));
  };
  
  
var updateSelectorData = function updateSelectorData(){
    var selectors = ['area', 'start_seconds', 'end_seconds'];
    var i, select;
    
    for (i=0; i<selectors.length; i++)
    {
      select = $('#' + selectors[i]);
      select.data('previous', select.data('current'));
      select.data('current', select.val());
    }
  };
  

function adjustSlotSelectors()
{
  <?php
  // Adjust the start and end time slot select boxes.
  // (a) If the start time has changed then adjust the end time so
  //     that the duration is still the same, provided that the endtime
  //     does not go past the start of the booking day
  // (b) If the end time has changed then adjust the duration.
  // (c) Make sure that you can't have an end time before the start time.
  // (d) Tidy up the two select boxes so that they are the same width
  // (e) if oldArea etc. are set, then we've switched areas and we want
  //     to have a go at finding a time/period in the new area as close
  //     as possible to the one that was selected in the old area.
  ?>
  var oldArea = $('#area').data('previous'),
      currentArea = $('#area').data('current');

  var enablePeriods    = areaConfig('enable_periods'),
      oldEnablePeriods = areaConfig('enable_periods', oldArea),
      defaultDuration  = areaConfig('default_duration');
  
  var startSelect = $('#start_seconds'),
      endSelect = $('#end_seconds'),
      allDay = $('#all_day');
      
  var startKeepDisabled = startSelect.hasClass('keep_disabled'),
      endKeepDisabled = endSelect.hasClass('keep_disabled'),
      allDayKeepDisabled = allDay.hasClass('keep_disabled');
      
  var oldStartValue = parseInt(startSelect.data('previous'), 10),
      oldEndValue = parseInt(endSelect.data('previous'), 10);
      
  var nbsp = '\u00A0',
      startValue, endValue, optionClone;
      
  if (startSelect.length === 0)
  {
    return;
  }
  <?php 
  // If All Day is checked then just set the start and end values to the first
  // and last possible options.
  ?>
  if (allDay.is(':checked'))
  {
    startValue = parseInt(startSelect.find('option').first().val(), 10);
    endValue = parseInt(endSelect.find('option').last().val(), 10);
    <?php
    // If we've come here from another area then we need to make sure that the
    // start and end selectors are disabled.  (We won't change the old_end and old_start
    // values, because there's a chance the existing ones may still work - for example if
    // the user flicks from Area A to Area B and then back to Area A, or else if the time/
    // period slots in Area B match those in Area.)
    ?>
    if (oldArea !== currentArea)
    {
      startSelect.prop('disabled', true);
      endSelect.prop('disabled', true);
    }
  }
  <?php
  // Otherwise what we do depends on whether we've come here as a result
  // of the area being changed
  ?>
  else if (oldArea !== currentArea)
  {
    <?php 
    // If we've changed areas and the modes are the same, we can try and match times/periods.
    // We will try and be conservative and find a start time that includes the previous start time
    // and an end time that includes the previous end time.   This means that by default the 
    // booking period will include the old booking period (unless we've hit the start or
    // end of day).   But it does mean that as you switch between areas the booking period
    // tends to get bigger:  if you switch fromn Area 1 to Area 2 and then back again it's
    // possible that the booking period for Area 1 is longer than it was originally.
    ?>
    if (oldEnablePeriods === enablePeriods)
    {
      <?php
      // Step back through the start options until we find one that is less than or equal to the previous value,
      // or else we've got to the first option
      ?>
      startSelect.find('option').reverse().each(function() {
          startValue = parseInt($(this).val(), 10);
          if (startValue <= oldStartValue)
          {
            return false;
          }
        });
      <?php
      // And step forward through the end options until we find one that is greater than
      // or equal to the previous value, or else we've got to the last option
      ?>
      endSelect.find('option').each(function() {
          endValue = parseInt($(this).val(), 10);
          if (endValue >= oldEndValue)
          {
            return false;
          }
        });
    }
    <?php
    // The modes are different, so it doesn't make any sense to match up old and new
    // times/periods.   The best we can do is choose some sensible defaults, which
    // is to set the start to the first possible start, and the end to the start + the
    // default duration (or the last possible end value if that is less)
    ?>
    else
    {
      startValue = parseInt(startSelect.find('option').first().val(), 10);
      if (enablePeriods)
      {
        endValue = startValue;
      }
      else
      {
        endValue = startValue + defaultDuration;
      }
    }
  }
  <?php 
  // We haven't changed areas.  In this case get the currently selected start and
  // end values
  ?>
  else  
  {
    startValue = parseInt(startSelect.val(), 10);
    endValue = parseInt(endSelect.val(), 10);
    <?php
    // If the start value has changed then we adjust the endvalue
    // to keep the duration the same.  (If the end value has changed
    // then the duration will be changed when we recalculate durations below)
    ?>
    if (startValue !== oldStartValue)
    {
      endValue = endValue + (startValue - oldStartValue);
    }
  }
    
  var dateDifference = getDateDifference();
    
  <?php
  // If All Day isn't checked then we need to work out whether the start
  // and end dates are valid.   If the end date is before the start date
  // then we disable all the time selectors (start, end and All Day) until
  // the dates are fixed.
  ?>
  if (!allDay.is(':checked'))
  {
    var newState = (dateDifference < 0);
    if (newState || startKeepDisabled)
    {
      startSelect.prop('disabled', true);
    }
    else
    {
      startSelect.prop('disabled', false);
    }
    if (newState || endKeepDisabled)
    {
      endSelect.prop('disabled', true);
    }
    else
    {
      endSelect.prop('disabled', false);
    }
    if (newState || allDayKeepDisabled)
    {
      allDay.prop('disabled', true);
    }
    else
    {
      allDay.prop('disabled', false);
    }
  }

  <?php // Destroy and rebuild the start select ?>
  startSelect.html($('#start_seconds' + currentArea).html());
  startSelect.val(startValue);
  startSelect.data('current', startValue);

  <?php // Destroy and rebuild the end select ?>
  endSelect.empty();

  $('#end_time_error').text('');  <?php  // Clear the error message ?>
 
  $('#end_seconds' + currentArea).find('option').each(function(i) {
  
      var thisValue = parseInt($(this).val(), 10),
          nPeriods           = areaConfig('n_periods'),
          maxDurationEnabled = areaConfig('max_duration_enabled'),
          maxDurationSecs    = areaConfig('max_duration_secs'),
          maxDurationPeriods = areaConfig('max_duration_periods'),
          maxDurationQty     = areaConfig('max_duration_qty'),
          maxDurationUnits   = areaConfig('max_duration_units'),
          secondsPerDay      = <?php echo SECONDS_PER_DAY ?>,
          duration,
          maxDuration;
     
      <?php
      // Limit the end slots to the maximum duration if that is enabled, if the
      // user is not an admin
      if (!$is_admin)
      {
        ?>
        if (maxDurationEnabled)
        {
          <?php
          // Calculate the duration in periods or seconds
          ?>
          duration =  thisValue - startValue;
          if (enablePeriods)
          {
            duration = duration/60 + 1;  <?php // because of the way periods work ?>
            duration += dateDifference * nPeriods;
          }
          else
          {
            duration += dateDifference * secondsPerDay;
          }
          maxDuration = (enablePeriods) ? maxDurationPeriods : maxDurationSecs;
          if (duration > maxDuration)
          {
            if (i === 0)
            {
              endSelect.append($(this).val(thisValue).text(nbsp));
              var errorMessage = '<?php echo escape_js(get_vocab("max_booking_duration")) ?>' + nbsp;
              if (enablePeriods)
              {
                errorMessage += maxDurationPeriods + nbsp;
                errorMessage += (maxDurationPeriods > 1) ? vocab.periods.plural : vocab.periods.singular;
              }
              else
              {
                errorMessage += maxDurationQty + nbsp + maxDurationUnits;
              }
              $('#end_time_error').text(errorMessage);
            }
            else
            {
              return false;
            }
          }
        }
        <?php
      }
      ?>
      if ((thisValue > startValue) ||
          ((thisValue === startValue) && enablePeriods) ||
          (dateDifference !== 0))
      {
        optionClone = $(this).clone();
        if (dateDifference < 0)
        {
          optionClone.text('<?php echo escape_js(get_vocab("start_after_end"))?>');
        }
        else
        {
          optionClone.text($(this).text() + nbsp + nbsp +
                           '(' + getDuration(startValue, thisValue, dateDifference) +
                           ')');
        }
        endSelect.append(optionClone);
      }
    });
  
  endValue = Math.min(endValue, parseInt(endSelect.find('option').last().val(), 10));
  endSelect.val(endValue);
  endSelect.data('current', endValue);
  
  adjustWidth(startSelect, endSelect);
    
} <?php // function adjustSlotSelectors() ?>


var editEntryVisChanged = function editEntryVisChanged() {
    <?php
    // Clear the conflict timer and then restart it.   We want
    // a check to be performed immediately the page becomes
    // visible again.
    ?>
    conflictTimer(false);
    conflictTimer(true);
  };

<?php
// =================================================================================

// Extend the init() function 
?>

var oldInitEditEntry = init;
init = function(args) {
  oldInitEditEntry.apply(this, [args]);
  
  <?php
  // If there's only one enabled area in the database there won't be an area
  // select input, so we'll have to create a dummy input because the code
  // relies on it.
  ?>
  if ($('#area').length === 0)
  {
    $('#div_rooms').before('<input id="area" type="hidden" value="' + args.area + '">');
  }
  
  var areaSelect = $('#area'),
      startSelect,
      endSelect,
      allDay;

  $('#div_areas').show();
  
  $('#start_seconds, #end_seconds')
      .each(function() {
          $(this).data('current', $(this).val());
          $(this).data('previous', $(this).val());
        })
      .change(function() {
          updateSelectorData();
          reloadSlotSelector($(this), $('#area').val());
          adjustSlotSelectors();
          updateSelectorData();
        });
    
  
  areaSelect
      .data('current', areaSelect.val())
      .data('previous', areaSelect.val())
      .change(function() {
          var newArea = $(this).val();

          updateSelectorData();
          
          <?php // Switch room selects ?>
          var roomSelect = $('#rooms');
          roomSelect.html($('#rooms' + newArea).html());
          
          <?php // Switch start time select ?>
          reloadSlotSelector($('#start_seconds'), newArea);
          
          <?php // Switch all day checkbox ?>
          var allDayCheckbox = $('#all_day');
          allDayCheckbox.html($('#all_day' + newArea).html());
          
          <?php // Switch end time select ?>
          reloadSlotSelector($('#end_seconds'), newArea);
          
          adjustSlotSelectors(); 
        });
        
  $('input[name="all_day"]').click(function() {
      onAllDayClick();
    });
    
  <?php
  // (1) put the booking name field in focus (but only for new bookings,
  // ie when the field is empty:  if it's a new booking you have to
  // complete that field, but if it's an existing booking you might
  // want to edit any field)
  // (2) Adjust the slot selectors
  // (3) Add some Ajax capabilities to the form (if we can) so that when
  //  a booking parameter is changed MRBS checks to see whether there would
  //  be any conflicts
  ?>
  var form = $('#main'),
      nameInput = form.find('#name');
  
  if (nameInput.length && !(nameInput.prop('disabled') || nameInput.val().length))
  {
    nameInput.focus();
  }
  
  adjustSlotSelectors();
  
  <?php
  // If this is an All Day booking then check the All Day box and disable the 
  // start and end time boxes
  ?>
  startSelect = form.find('#start_seconds');
  endSelect = form.find('#end_seconds');
  allDay = form.find('#all_day');
  if ((allDay.is(':disabled') === false) && 
      (startSelect.val() === startSelect.find('option').first().val()) &&
      (endSelect.val() === endSelect.find('option').last().val()))
  {
    allDay.attr('checked', 'checked');
    startSelect.prop('disabled', true);
    endSelect.prop('disabled', true);
    onAllDayClick.oldStart = startSelect.val();
    onAllDayClick.oldEnd = endSelect.val();
    onAllDayClick.oldStartDatepicker = form.find('#start_datepicker').datepicker('getDate');
    onAllDayClick.oldEndDatepicker = form.find('#end_datepicker').datepicker('getDate');
  }



  <?php
  // Set up the validation messages, but only if the function exists (which it
  // won't if we're on the login page)
  ?>
  if (typeof validationMessages === 'function')
  {
    validationMessages();
  }
  
  <?php
  // If anything like a submit button is pressed then add a data flag to the form so
  // that the function that checks for a valid booking can see if the change was
  // triggered by a Submit button being pressed, and if so, not to send an Ajax request.
  ?>
  form.find('[type="submit"], [type="button"], [type="image"]').click(function() {
    var trigger = $(this).attr('name');
    $(this).closest('form').data('submit', trigger);
  });

  form.on('submit', function() {
      if ($(this).data('submit') === 'save_button')
      {
        <?php // Only validate the form if the Save button was pressed ?>
        var result = validate($(this));
        if (!result)
        {
          <?php // Clear the data flag if the validation failed ?>
          $(this).removeData('submit');
        }
        return result;
      }
      return true;
    });
      
  <?php
  // Add Ajax capabilities (but only if we can return the result as a JSON object)
  if (function_exists('json_encode'))
  {
    // Add a change event handler to each of the form fields - except for those that
    // are disabled and anything that might be a submit button - so that when they change
    // the validity of the booking is re-checked.   (This probably causes more checking
    // than is really necessary, eg when the brief description is changed, but on the other
    // hand it (a) removes the need to know the names of the fields you want and (b) keeps
    // the data available for policy checking as complete as possible just in case somebody
    // decides to set a policy based on for example the brief description, for some reason).
    //
    // Use a click event for checkboxes as it seems that in some browsers the event fires
    // before the value is changed.
    ?>
    var formFields = $('form#main [name]').not(':disabled, [type="submit"], [type="button"], [type="image"]');
    formFields.filter(':checkbox')
              .click(function() {
                  checkConflicts();
                });
    formFields.not(':checkbox')
              .change(function() {
                  checkConflicts();
                });
     
    $('#conflict_check, #policy_check').click(function manageTabs() {
        var tabId,
            tabIndex,
            checkResults = $('#check_results'),
            checkTabs = $('#check_tabs');
        <?php 
        // Work out which tab should be selected
        // (Slightly long-winded using a switch, but there may be more tabs in future)
        ?>
        switch ($(this).attr('id'))
        {
          case 'policy_check':
            tabId = 'policy_details';
            break;
          case 'conflict_check':
          default:
            tabId = 'schedule_details';
            break;
        }
        tabIndex = $('#details_tabs a[href="#' + tabId + '"]').parent().index();

        <?php
        // If we've already created the dialog and tabs, then all we have
        // to do is re-open the dialog if it has previously been closed and
        // select the tab corresponding to the div that was clicked
        ?>
        if (manageTabs.alreadyExists)
        {
          if (!checkResults.dialog("isOpen"))
          {
            checkResults.dialog("open");
          }
          checkTabs.tabs('option', 'active', tabIndex);
          return;
        }
        <?php
        // We want to create a set of tabs that appear inside a dialog box,
        // with the whole structure being draggable.   Thanks to dbroox at
        // http://forum.jquery.com/topic/combining-ui-dialog-and-tabs for the solution.
        ?>
        checkTabs.tabs();
        checkTabs.tabs('option', 'active', tabIndex);
        checkResults.dialog({'width': 400,
                             'height': 200, 
                             'minWidth': 300,
                             'minHeight': 150, 
                             'draggable': true});
        <?php //steal the close button ?>
        $('#details_tabs').append($('button.ui-dialog-titlebar-close'));
        <?php //move the tabs out of the content and make them draggable ?>
        $('.ui-dialog').addClass('ui-tabs')
                       .prepend($('#details_tabs'))
                       .draggable('option', 'handle', '#details_tabs');
        <?php //switch the titlebar class ?>
        $('.ui-dialog-titlebar').remove();
        $('#details_tabs').addClass('ui-dialog-titlebar');
        
        manageTabs.alreadyExists=true;
      });
    
    <?php
    // Finally, set a timer so that conflicts are periodically checked for,
    // in case someone else books that slot before you press Save.
    ?>
    conflictTimer(true);
    
    <?php
  } // if (function_exists('json_encode'))

  // Actions to take when the repeat end datepicker is updated (it doesn't fire
  // a change event so won't be caught by the general handler above)
  ?>
  $('#rep_end_datepicker').on('datePickerUpdated', function() {
    checkConflicts();
  });
  
  
  <?php
  // Actions to take when the start and end datepickers are closed
  ?>
  $('#start_datepicker, #end_datepicker').on('datePickerUpdated', function() {
    
    <?php
    // (1) If the end_datepicker isn't visible and we change the start_datepicker,
    //     then set the end date to be the same as the start date.  (This will be
    //     the case if multiday bookings are not allowed)
    ?>
    if ($(this).attr('id') === 'start_datepicker')
    {
      if ($('#end_datepicker').css('visibility') === 'hidden')
      {
        $('#end_datepicker_alt').val($('#start_datepicker_alt').val());
      }
    }
    
    <?php
    // (2) Go and adjust the start and end time/period select options, because
    //     they are dependent on the start and end dates
    ?>
    adjustSlotSelectors();
    
    <?php
    // (3) If we're doing Ajax checking of the form then we have to check
    //     for conflicts when the datepicker is closed
    ?>
    checkConflicts();
      
    <?php
    // (4) Check to see whether any time slots should be removed from the time
    //     select on the grounds that they don't exist due to a transition into DST.
    ?>
    checkTimeSlots($(this));

  });
  
  $('#start_datepicker, #end_datepicker').each(function() {
      checkTimeSlots($(this));
    });
    
  $('input[name="rep_type"]').change(changeRepTypeDetails);
  changeRepTypeDetails();
  
  <?php
  // Add an event listener to detect a change in the visibility
  // state.  We can then suspend Ajax checking when the page is
  // hidden to save on server, client and network load.
  ?>
  var prefix = visibilityPrefix();
  if (document.addEventListener &&
      (prefix !== null))
  {
    document.addEventListener(prefix + "visibilitychange", editEntryVisChanged);
  }
};
