Installing OpenFlights on Ubuntu 14.04 LTS
==========================================

The following assumes you have a fresh install of Ubuntu 14.04 and you're logged in as 'ubuntu' with sudo rights.
This is intended as a recipe to be followed by hand, not a fire-and-forget script that can do the job automatically.

---

# Set up LAMP, Git and Sendmail (password resets & donations)
```
sudo apt-get install apache2 git php5 php5-curl php5-gd php5-mysql mysql-server sendmail
```

# Enable Apache modules (we'll restart later)
```
sudo a2enmod include
sudo a2enmod rewrite
```

# Checkout a copy of OpenFlights into /var/www
```
cd /var/www
sudo git clone https://github.com/jpatokal/openflights.git
```

# Set up Composer and install packages
```
curl -sS https://getcomposer.org/installer | sudo php -- --install-dir=/usr/local/bin --filename=composer
sudo php /usr/local/bin/composer install
```

# Generates locales for language support
```
cd openflights
find locale -name *utf8 -printf '%f ' | xargs sudo locale-gen
```

# Enable uploads and badge caching
```
sudo mkdir import
sudo mkdir badge/cache
sudo chown www-data:www-data import badge/cache
```

# Set up database (alternatively, if restoring from backup, just run the first line in create.sql to
# create DB and user, then import the dump)
```
mysql -u root -p <sql/create.sql
mysql -u openflights flightdb2 --local-infile <sql/load-data.sql
```

# Set up local config for database, locals, Github etc.
# Beware of linefeeds after the ?>, will cause breakage!
`sudo cp php/config.php.sample php/config.php`

# Set up TripIt (if you need it)
```
sudo vi php/secrets.php
<<<
$tripit_app_id = "[YOUR-ID]";
$tripit_app_secret = "[YOUR-APP-SECRET]"
>>>
```

# Tell Apache where to find OpenFlights
```
sudo cp apache2/openflights.conf /etc/apache2/sites-available/
cd /etc/apache2/sites-enabled
sudo ln -s ../sites-available/openflights.conf openflights.conf
sudo service apache2 restart
```
