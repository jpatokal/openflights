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
RUN add-apt-repository ppa:ondrej/php -y # 22.04 natively supports only up to 8.1
RUN apt-get install -y \
    php8.3 php8.3-curl php8.3-fpm php8.3-gd php8.3-intl php8.3-mbstring php8.3-mysql php8.3-xml
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
RUN sed -i -e '/catch_workers_output =/s/.*/catch_workers_output = yes/' /etc/php/8.3/fpm/pool.d/www.conf
RUN phpenmod gettext

# Copy current directory (repo) into container
COPY . /var/www/openflights

# nginx setup
RUN rm /etc/nginx/sites-enabled/default
RUN ln -s /var/www/openflights/nginx/openflights-dev /etc/nginx/sites-available/
RUN ln -s /etc/nginx/sites-available/openflights-dev /etc/nginx/sites-enabled/

# Run on port 80
EXPOSE 80
CMD ["/bin/bash", "-c", "/etc/init.d/php8.3-fpm start; /etc/init.d/nginx start; tail -f /var/log/php8.3-fpm.log"]
