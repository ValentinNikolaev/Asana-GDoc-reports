# setup
mkdir -m 755 credentials && mkdir -m 755 reports && mkdir -m 755 tmp
sudo chown -R www-data:www-data credentials/

# Run from Cli to generateAsana reports

1. Run php google-cli.php
2. Go via link and copy access code
3. Insert code at Cli

# Cli options

Usage: google-cli.php [options] [operands]

Options:

  -r, --refresh_token     Refresh token if exists
  
  -d, --remove            Remove credentials to authorize new user
  
  -v, --version           Display version information

# Send drafts
1. To get latest report list, run google-serverside.php from browser

