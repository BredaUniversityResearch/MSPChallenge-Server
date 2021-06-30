<footer style="text-align: center; padding-top: 15px;">

  <div>
    MSP Challenge Server version <?php echo ServerManager::getInstance()->GetCurrentVersion(); ?>
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
