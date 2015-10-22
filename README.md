# notes
master branch is for test only. Check v2 branch for updates.

# setup
mkdir -m 755 credentials && mkdir -m 755 reports && mkdir -m 755 tmp
sudo chown -R www-data:www-data credentials/

# Usage
1. Copy config-sample.php to config.php. Make needed changes
2. Chande permissions at credentials and tmp folders to 777
3. Run connect.php from browser. Connect your google account with script
4. Run cli.php via Cli.
5. Run doc_list.php from browser to get latest report list

# Connect another account
1. Run php cli.php -d from Cli
2. Run connect.php from browser.

# Cli options

Usage: cli.php [options] [operands]

Options:

  -r, --refresh_token     Refresh token if exists
  
  -d, --remove            Remove credentials to authorize new user
  
  -v, --version           Display version information

# Send drafts
1. Run doc_list.php from browser to get latest report list

