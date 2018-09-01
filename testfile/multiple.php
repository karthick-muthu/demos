<html>
<head>

<body onload="onLoad()">
<input  type="hidden" id="myStateInput">
</body>
</html>

<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.3.1/jquery.min.js"></script>
<script >

function onLoad()
{
   if ($('#myStateInput').val() === 'already_loaded') {
   	  alert("Duplicate tab! Do something.");
   }else if ($('#myStateInput').val() === '') {
        $('#myStateInput').val('already_loaded');
   }
   
}
</script></head>
