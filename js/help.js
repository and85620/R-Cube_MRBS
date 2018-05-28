$(document).ready(function(){
    $('#SystemInterview').hide();
    $('#RecentRule').hide();
})
function showSystem(){
    $('#SystemInterview').show();
    $('#RecentRule').hide();
}
function showRule(){
    $('#SystemInterview').hide();
    $('#RecentRule').show();
}