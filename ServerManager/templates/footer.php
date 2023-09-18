<footer>
    <div id="footer">
        MSP Challenge is a community-based, open source and not for profit initiative, since 2011.
    </div>
    <div id="footer-extra">
        <img src="/ServerManager/images/EU_flag_yellow_high.jpg" style="width: 40px;"/>
        Co-funded by the European Union.
    </div>
    <div>
        <p></p>
    </div>
    <div>
        MSP Challenge Server version
        <?php
        use ServerManager\ServerManager;

        echo ServerManager::getInstance()->getCurrentVersion();
        ?>
    </div>
    <div>
        Server Address:
        <?php
        echo ServerManager::getInstance()->getTranslatedServerURL();
        ?>
    </div>
</footer>
</body>
</html>
