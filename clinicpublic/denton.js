$(document).ready(function() {
// Function to change form action.
$("#clinic").change(function() {
var selected = $(this).children(":selected").text();
switch (selected) {
case "denton":
$("#myform").attr('action', 'https://denton.clinic.notepro.co/authenticate2.php');
break;
case "denton2":
$("#myform").attr('action', 'https://denton2.clinic.notepro.co/authenticate2.php');
break;
case "ffltest":
$("#myform").attr('action', 'https://ffltest.clinic.notepro.co/authenticate.php');
break;
default:
$("#myform").attr('action', '#');
}
});
// Function For Back Button
$(".back").click(function() {
parent.history.back();
return false;
});
});
