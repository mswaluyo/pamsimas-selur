# ============================================================
# PAMSIMAS - Multi-Stage Dockerfile for Fly.io
# Base: Debian Bookworm Slim
# Stack: Nginx + PHP 8.2-FPM + Python 3 (EasyOCR/OpenCV)
# ============================================================

# Stage 1: Build Python dependencies
FROM debian:bookworm-slim AS python-deps

WORKDIR /tmp/python-deps

RUN apt-get update && apt-get install -y --no-install-recommends \
    python3 python3-pip python3-venv \
    && rm -rf /var/lib/apt/lists/*

RUN python3 -m venv /opt/venv
ENV PATH="/opt/venv/bin:$PATH"

RUN pip install --no-cache-dir torch torchvision --index-url https://download.pytorch.org/whl/cpu

RUN pip install --no-cache-dir \
    opencv-python-headless numpy easyocr

# Stage 2: Main Application Image
FROM debian:bookworm-slim

ENV DEBIAN_FRONTEND=noninteractive

RUN apt-get update && apt-get install -y --no-install-recommends \
    nginx \
    php8.2-fpm php8.2-cli php8.2-common php8.2-mysql php8.2-pgsql \
    php8.2-sqlite3 php8.2-curl php8.2-gd php8.2-mbstring php8.2-xml \
    php8.2-zip php8.2-bcmath php8.2-intl php8.2-opcache php8.2-readline \
    python3 python3-venv \
    curl unzip git ca-certificates \
    libpng-dev libjpeg-dev libfreetype6-dev libwebp-dev libzip-dev \
    libonig-dev libxml2-dev libcurl4-openssl-dev libssl-dev \
    libgl1-mesa-glx libglib2.0-0 libsm6 libxext6 libxrender-dev \
    libgomp1 libopenblas-dev \
    && rm -rf /var/lib/apt/lists/*

# Copy Python venv from Stage 1
COPY --from=python-deps /opt/venv /opt/venv
ENV PATH="/opt/venv/bin:$PATH"

# Configure PHP-FPM
RUN sed -i 's/;cgi.fix_pathinfo=1/cgi.fix_pathinfo=0/' /etc/php/8.2/fpm/php.ini \
    && sed -i 's/memory_limit = .*/memory_limit = 512M/' /etc/php/8.2/fpm/php.ini \
    && sed -i 's/upload_max_filesize = .*/upload_max_filesize = 20M/' /etc/php/8.2/fpm/php.ini \
    && sed -i 's/post_max_size = .*/post_max_size = 25M/' /etc/php/8.2/fpm/php.ini \
    && sed -i 's/max_execution_time = .*/max_execution_time = 120/' /etc/php/8.2/fpm/php.ini \
    && sed -i 's/max_input_time = .*/max_input_time = 120/' /etc/php/8.2/fpm/php.ini \
    && sed -i 's/disable_functions =.*/disable_functions = passthru,system,popen,parse_ini_file,show_source/' /etc/php/8.2/fpm/php.ini

# Configure Nginx
COPY nginx-default.conf /etc/nginx/sites-available/default
RUN rm -f /etc/nginx/sites-enabled/default 2>/dev/null || true

WORKDIR /var/www/html

# Copy application code
COPY deploy/release/ /var/www/html/

# Install Composer dependencies
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
    && cd /var/www/html \
    && composer install --optimize-autoloader --no-dev --no-interaction --no-progress \
    && composer clear-cache

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/storage \
    && chmod -R 775 /var/www/html/bootstrap/cache \
    && mkdir -p /var/www/html/storage/app/meter_photos \
    && mkdir -p /var/www/html/storage/framework/cache \
    && mkdir -p /var/www/html/storage/framework/sessions \
    && mkdir -p /var/www/html/storage/framework/views \
    && mkdir -p /var/www/html/storage/logs \
    && mkdir -p /var/www/html/storage/app/.easyocr/model \
    && mkdir -p /var/www/html/storage/app/.easyocr/user_network \
    && chown -R www-data:www-data /var/www/html/storage \
    && chown -R www-data:www-data /var/www/html/bootstrap/cache

# Copy entrypoint
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Environment
ENV EASYOCR_MODULE_PATH=/var/www/html/storage/app/.easyocr/model
ENV EASYOCR_USER_NETWORK_DIRECTORY=/var/www/html/storage/app/.easyocr/user_network
ENV PYTHON_PATH=/opt/venv/bin/python3

# Expose & Health Check
EXPOSE ${PORT:-8080}

# Use PORT from environment (Render/Fly.io compatible)
ENV PORT=8080

HEALTHCHECK --interval=30s --timeout=5s --start-period=60s --retries=3 \
    CMD curl -f http://127.0.0.1:${PORT}/health || exit 1

ENTRYPOINT ["docker-entrypoint.sh"]
EXPOSE 8080

HEALTHCHECK --interval=30s --timeout=5s --start-period=60s --retries=3 \
    CMD curl -f http://127.0.0.1:8080/health || exit 1

ENTRYPOINT ["docker-entrypoint.sh"]