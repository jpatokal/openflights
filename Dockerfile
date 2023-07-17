FROM ubuntu:22.04

ARG DEBIAN_FRONTEND=noninteractive
ENV TZ=Etc/UTC

RUN apt-get update && apt-get install -y \
    apt-transport-https \
    curl \
    git \
    locales \
    mysql-client \
    nginx \
    software-properties-common \
    unzip \
    tzdata \
    vim

# System-level PHP setup
RUN add-apt-repository ppa:ondrej/php -y
RUN apt-get install -y \
    php7.4 php7.4-curl php7.4-fpm php7.4-gd php7.4-mbstring php7.4-mysql php7.4-xml
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
RUN sed -i -e '/catch_workers_output =/s/.*/catch_workers_output = yes/' /etc/php/7.4/fpm/pool.d/www.conf
RUN phpenmod gettext

# nginx setup
# note that these symlinks require the repo be mounted @ /var/www/openflights
RUN rm /etc/nginx/sites-enabled/default
RUN ln -s /var/www/openflights/nginx/openflights-dev /etc/nginx/sites-available/
RUN ln -s /etc/nginx/sites-available/openflights-dev /etc/nginx/sites-enabled/

CMD ["/bin/bash", "-c", "/etc/init.d/php7.4-fpm start; /etc/init.d/nginx start; tail -f /var/log/php7.4-fpm.log"]
