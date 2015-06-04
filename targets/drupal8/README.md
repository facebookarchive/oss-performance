Target Assembly
---------------

What follows is a summary of the shell commands I used to assemble the Drupal 8
targets (meaning the static files, database dumps, and settings files) which
are included.

```
# Get the latest beta.
wget http://ftp.drupal.org/files/projects/drupal-8.0.0-beta11.tar.gz
tar -xzf drupal-8.0.0-beta11.tar.gz

# Install the site via Drush.
drush si --db-url=mysql://drupal_bench:drupal_bench@localhost:3306/drupal_bench \
  --site-mail="root@localhost" \
  --site-name="Drupal 8 OSS Performance Test (No Page Cache)" \
  --account-mail="root@localhost" \
  --account-name="root" \
  --account-pass="root"

# Turn off the page cache and turn on devel_generate
drush pmu page_cache -y
drush dl devel
drush en devel_generate -y
drush cr

# Generate users, terms, menus, content.
# Content generation command didn't work so I had to do that through the UI.
drush genu 50 --kill
drush gent tags 50 --kill
drush genm 2 50 3 8 --kill
drush genc 50 30 --kill

# Fix the files directory.
sudo chmod -R a+wr sites/default/files
drush cr

# Clear out random crap.
`drush sql-connect` -e "TRUNCATE watchdog; TRUNCATE history; TRUNCATE flood;"

# Tar up content and database.
cd sites/default
tar -cjf demo-static.tar.bz2 files/
drush sql-dump --result-file=dbdump-nocache.sql --gzip

# Turn on page cache, rebuild internal caches, and dump DB again.
drush en page_cache -y
drush config-set system.site name "Drupal 8 OSS Performance Test (Page Cache)" -y
drush cr
drush sql-dump --result-file=dbdump-pagecache.sql --gzip
```

Drush
-----

Drush is packaged as a tarball of the Drush repo (with the named commit hash)
and a matching vendor directory (packaged separately) constructed by Composer.

E.g.:

```
git clone https://github.com/drush-ops/drush.git
cd drush
git rev-parse HEAD 
# 8ba1fbe in this example
cd ..
tar -cjf drush-8ba1fbe.tar.bz2 drush
cd drush
composer install
cd ..
tar -cjf drush-8ba1fbe-vendor.tar.bz2
```


