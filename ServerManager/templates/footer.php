
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js" integrity="sha384-Tc5IQib027qvyjSMfHjOMaLkfuWVxZxUPnCJA7l2mCWNIpG9mGCD8wGNIcPD7Txa" crossorigin="anonymous"></script>


<script type="text/javascript">
$(document).ready(function(){
  $('[data-toggle="tooltip"]').tooltip();
});
</script>



<footer style="text-align: center; padding-top: 15px;">

<div>
  MSP Challenge Server version <?php echo ThisServerVersion(); ?>
</div>
<div>
  Server Address: 
  <?php 
  $address = ServerManager::getInstance()->GetTranslatedServerURL();
  if ($address == "localhost") {
    $address .= "<br/>Translated automatically to ".gethostbyname(gethostname());
  }
  echo $address;
  ?>
</div>

</footer>
</body>
</html>
