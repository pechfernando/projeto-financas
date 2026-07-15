FROM php:8.3-apache

# Instala a extensão do PHP para conversar com MySQL via PDO
RUN docker-php-ext-install pdo pdo_mysql

# Habilita mod_rewrite do Apache (útil para rotas amigáveis depois)
RUN a2enmod rewrite

# Aponta o servidor web para a pasta /public dentro do projeto
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

WORKDIR /var/www/html
