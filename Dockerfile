FROM php:8.4-cli

# ដំឡើង Tools ផ្សេងៗ និង Libraries សម្រាប់ដំណើរការ GD Extension
RUN apt-get update && apt-get install -y \
    unzip \
    git \
    curl \
    libzip-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_mysql zip gd \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

# ==========================================
# 🌟 ចំណុចសំខាន់ដែលធ្វើឲ្យ Deploy លឿន
# ==========================================
# ១. ចម្លងតែឯកសារចាំបាច់សម្រាប់ Composer សិន
COPY composer.json composer.lock* ./

# ២. ដំឡើង Composer (វានឹងចងចាំទុកជារៀងរហូត លុះត្រាតែអ្នកថែម Package ថ្មី)
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
RUN composer install --no-dev --optimize-autoloader --no-scripts

# ៣. ចម្លងកូដទាំងអស់ (Controllers, Routes, Views...) ចូលតាមក្រោយ
COPY . .

# ផ្តល់សិទ្ធិ
RUN chmod -R 777 storage bootstrap/cache

# បញ្ជាឲ្យដំណើរការ Server
CMD php artisan serve --host=0.0.0.0 --port=$PORT