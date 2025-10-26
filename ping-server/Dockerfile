# --- Stage 2: PHP + Apache ---
FROM php:8.2-apache

# Bật mod_rewrite (nếu sau này cần router)
RUN a2enmod rewrite

# Thiết lập timezone (tùy chọn)
RUN echo "date.timezone=Asia/Ho_Chi_Minh" > /usr/local/etc/php/conf.d/timezone.ini

# Copy code vào web root
WORKDIR /var/www/html
COPY . /var/www/html

# Quyền (tùy code)
RUN chown -R www-data:www-data /var/www/html

# Apache mặc định nghe cổng 80
EXPOSE 80

# Healthcheck đơn giản
HEALTHCHECK --interval=30s --timeout=5s --retries=3 CMD curl -f http://localhost/ || exit 1