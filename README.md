# setup
mkdir -m 755 credentials && mkdir -m 755 reports && mkdir -m 755 tmp

# Run from Cli to generateAsana reports

1. Update composer
2. Set DAILY_REPORT_TEMPLATE constant at config.php for your gdoc url. You can find it at page url.
3. Run step 1 from here https://developers.google.com/drive/web/quickstart/php
4. Copy client_secret.json to a root
5. Run php google-cli.php

# Send drafts
1. Run step 1 from here https://developers.google.com/gmail/api/quickstart/php
2. To get latest report list, run google-serverside.php from browser
