#!/bin/sh
sudo apt-get -y install nginx unzip mysql-server util-linux coreutils
sudo apt-get -y install autotools-dev
sudo apt-get -y install autoconf
sudo apt-get -y install software-properties-common build-essential
sudo apt-key adv --recv-keys --keyserver hkp://keyserver.ubuntu.com:80 0x5a16e7281be7a449
sudo add-apt-repository "deb http://dl.hhvm.com/ubuntu xenial main"
sudo apt-get update
sudo apt-get -y install hhvm
sudo apt-get -y install php7.0 php7.0-cgi php7.0-fpm
sudo apt-get -y install php7.0-mysql php7.0-curl php7.0-gd php7.0-intl php-pear php-imagick php7.0-imap php7.0-mcrypt php-memcache  php7.0-pspell php7.0-recode php7.0-sqlite3 php7.0-tidy php7.0-xmlrpc php7.0-xsl php7.0-mbstring php-gettext

git clone https://github.com/JoeDog/siege.git
cd siege
git checkout tags/v4.0.3rc3
./utils/bootstrap
automake --add-missing
./configure
make
sudo make uninstall
sudo make install
cd ..
rm -rf siege

cd "$(dirname "$0")"
cd ..
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php -r "if (hash_file('SHA384', 'composer-setup.php') === '55d6ead61b29c7bdee5cccfb50076874187bd9f21f65d8991d46ec5cc90518f447387fb9f76ebae1fbbacf329e583e30') { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;"
php composer-setup.php
php -r "unlink('composer-setup.php');"

php composer.phar install

for file in /sys/devices/system/cpu/cpu*/cpufreq/scaling_governor; do
  sudo -- sh -c "echo performance > $file"
done

echo 1 | sudo tee /proc/sys/net/ipv4/tcp_tw_reuse

echo 1 | sudo tee /sys/devices/system/cpu/intel_pstate/no_turbo || true
