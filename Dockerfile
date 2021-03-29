FROM php:apache
COPY . /var/www/html
RUN echo "DirectoryIndex index.php" >> /var/www/html/.htaccess